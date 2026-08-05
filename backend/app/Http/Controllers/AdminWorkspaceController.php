<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\BillingChange;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Managing workspaces from the admin panel.
 *
 * Billing used to be edited through a *user*, with the controller quietly resolving which
 * of their workspaces the change would land on. That made the one screen where money is
 * set the one screen that could not say what it was editing. It is edited here instead,
 * on the thing that holds the subscription, uses the units and receives the invoice.
 *
 * Guarded by ChecksAdminAccess called first in every action, matching AdminController and
 * AdminMergeController. There is deliberately no admin route middleware in this app.
 */
class AdminWorkspaceController extends Controller
{
    use ChecksAdminAccess;

    /**
     * Every workspace, with what it costs.
     */
    public function index(): View
    {
        $this->checkAdminAccess();

        $search = request()->get('search');

        // Usage comes off the counter columns, so no per-row counting: the list stays one
        // query however many workspaces there are.
        $query = Workspace::query()
            ->with(['billingOwnerUser', 'subscriptions', 'members'])
            ->withCount('members');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('members', function ($member) use ($search) {
                        $member->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%");
                    });
            });
        }

        $workspaces = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('pages.admin.workspaces.index', [
            'workspaces' => $workspaces,
            'mrrByWorkspace' => $this->mrrFor($workspaces->pluck('id')->all()),
        ]);
    }

    /**
     * One workspace: what it uses, what it pays, and who is in it.
     */
    public function show(Workspace $workspace): View
    {
        $this->checkAdminAccess();

        $workspace->load([
            'members' => fn ($query) => $query->orderBy('name'),
            'billingOwnerUser',
            'subscriptions',
        ]);

        try {
            $analyticsRow = DB::table('analytics_workspaces')
                ->where('workspace_id', $workspace->id)
                ->first();
        } catch (\Exception $e) {
            $analyticsRow = null;
        }

        try {
            $billingChanges = BillingChange::where('workspace_id', $workspace->id)
                ->orderByDesc('detected_at')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            $billingChanges = collect();
        }

        $usage = $workspace->getUsageBreakdown();

        return view('pages.admin.workspaces.show', [
            'workspace' => $workspace,
            'usage' => $usage,
            // Floored at one unit, the same as the invoice: a workspace with nothing in it
            // still pays for one.
            'billableUnits' => max(1, $usage['total']),
            'unitPrice' => $workspace->getManualBillingUnitPrice(),
            'globalUnitPrice' => config('settings.unit_price'),
            'analyticsRow' => $analyticsRow,
            'billingChanges' => $billingChanges,
        ]);
    }

    /**
     * Set how this workspace is billed.
     *
     * A manually billed workspace is invoiced through our own accounting rather than Lemon
     * Squeezy: it gets Pro without a subscription, and its MRR is computed locally from
     * usage at the unit price set here.
     */
    public function updateBilling(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->checkAdminAccess();

        $validated = $request->validate([
            'is_manually_billed' => ['nullable', 'boolean'],
            'manual_billing_unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'billing_owner_user_id' => ['nullable', 'string', 'exists:users,id'],
        ]);

        $price = $validated['manual_billing_unit_price'] ?? null;
        $owner = $validated['billing_owner_user_id'] ?? null;

        // Only a member can carry the billing. A pointer at someone outside the workspace
        // would leave the invoice addressed to nobody the next time it is resolved.
        if ($owner && ! $workspace->members()->where('users.id', $owner)->exists()) {
            return back()->with('error', 'That user is not a member of this workspace.');
        }

        $workspace->update([
            'is_manually_billed' => $request->boolean('is_manually_billed'),
            'manual_billing_unit_price' => $price === '' ? null : $price,
            'billing_owner_user_id' => $owner ?? $workspace->billing_owner_user_id,
        ]);

        logger()->info('Admin updated workspace billing', [
            'workspace_id' => $workspace->id,
            'admin_id' => Auth::id(),
            'is_manually_billed' => $workspace->is_manually_billed,
            'manual_billing_unit_price' => $workspace->manual_billing_unit_price,
            'billing_owner_user_id' => $workspace->billing_owner_user_id,
        ]);

        $this->refreshManualMrr($workspace);

        return back()->with('success', 'Billing settings updated.');
    }

    /**
     * The workspace that carries a user's billing.
     *
     * Used by the admin user page to link through to the right workspace.
     */
    public static function billingWorkspaceFor(User $user): ?Workspace
    {
        return Workspace::where('billing_owner_user_id', $user->id)->orderBy('id')->first()
            ?? $user->ownedWorkspaces()->orderByPivot('created_at')->first()
            ?? $user->primaryWorkspace();
    }

    /**
     * Recompute the stored MRR right away, so the screen reflects a new unit price instead
     * of the value from the last analytics refresh.
     *
     * Only while manually billed: once the flag is off, Lemon Squeezy is the only source
     * for MRR, so the row is left for the scheduled refresh to re-derive.
     */
    private function refreshManualMrr(Workspace $workspace): void
    {
        if (! $workspace->is_manually_billed) {
            return;
        }

        try {
            $mrr = $workspace->calculateManualMrr();

            DB::table('analytics_workspaces')
                ->where('workspace_id', $workspace->id)
                ->update([
                    'subscription_status' => 'manual',
                    'billing_interval' => 'monthly',
                    'mrr_current' => $mrr,
                    'mrr_expected' => $mrr,
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            // Analytics table isn't present (e.g. self-hosted) — the scheduled refresh will catch up.
        }
    }

    /**
     * Last known MRR per workspace, for the list.
     *
     * @param  array<int, string>  $workspaceIds
     * @return array<string, float>
     */
    private function mrrFor(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        try {
            return DB::table('analytics_workspaces')
                ->whereIn('workspace_id', $workspaceIds)
                ->pluck('mrr_current', 'workspace_id')
                ->map(fn ($mrr) => (float) $mrr)
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }
}
