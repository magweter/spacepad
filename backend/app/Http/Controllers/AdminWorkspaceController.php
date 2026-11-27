<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\BillingChange;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AdminPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
    /** Above this many displays a Pro workspace is an Enterprise customer, as on the pricing page. */
    private const ENTERPRISE_DISPLAYS = 25;

    use ChecksAdminAccess;

    /**
     * Find workspaces for a picker (the merge screen): by workspace name, by a member's name or
     * email, or by its ULID. Each comes with its owner's email, because workspace names alone are
     * often unclear ("Martijn's Workspace").
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->checkAdminAccess();

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['workspaces' => []]);
        }

        $workspaces = Workspace::query()
            ->with(['billingOwnerUser', 'members'])
            ->withCount(['members', 'displays'])
            ->where(fn ($query) => $query
                ->where('id', $q)
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhereHas('members', fn ($member) => $member
                    ->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json([
            'workspaces' => $workspaces->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'owner' => $workspace->billingOwner()?->email,
                'members' => $workspace->members_count,
                'displays' => $workspace->displays_count,
            ])->values()->all(),
        ]);
    }

    /**
     * Every workspace, with what it costs.
     */
    public function index(Request $request): Response
    {
        $this->checkAdminAccess();

        $search = $request->get('search');

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

        $mrrByWorkspace = $this->mrrFor($workspaces->pluck('id')->all());

        $workspaces->through(function (Workspace $workspace) use ($mrrByWorkspace) {
            $owner = $workspace->billingOwner();
            $mrr = $mrrByWorkspace[$workspace->id] ?? null;

            return [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'owner' => $owner ? ['email' => $owner->email, 'url' => route('admin.users.show', $owner)] : null,
                'members' => (int) $workspace->members_count,
                'displays' => (int) $workspace->displays_count,
                'boards' => (int) $workspace->boards_count,
                'units' => $workspace->getTotalUsageCount(),
                'plan' => self::planBadge($workspace, (int) $workspace->displays_count),
                'mrr' => $mrr === null ? null : '$'.number_format($mrr, 2),
                'created' => $workspace->created_at?->format('Y-m-d'),
                'url' => route('admin.workspaces.show', $workspace),
            ];
        });

        return Inertia::render('Admin/Workspaces', [
            ...AdminPage::props($request),
            'workspaces' => $workspaces,
            'search' => $search ?? '',
            'urls' => ['index' => route('admin.workspaces.index')],
        ]);
    }

    /**
     * One workspace: what it uses, what it pays, and who is in it.
     */
    public function show(Request $request, Workspace $workspace): Response
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
        // Floored at one unit, the same as the invoice: a workspace with nothing in it
        // still pays for one.
        $billableUnits = max(1, $usage['total']);
        $unitPrice = $workspace->getManualBillingUnitPrice();
        $globalUnitPrice = config('settings.unit_price');
        $owner = $workspace->billingOwner();

        return Inertia::render('Admin/Workspace', [
            ...AdminPage::props($request),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'created' => $workspace->created_at?->format('d M Y H:i'),
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'url' => route('admin.users.show', $owner),
                ] : null,
                'plan' => self::planBadge($workspace),
                'hasLiveSubscription' => (bool) $workspace->liveSubscription(),
            ],
            'usage' => [
                'displays' => $usage['displays'],
                'boards' => $usage['boards'],
                'total' => $usage['total'],
            ],
            'subscription' => $analyticsRow ? [
                'status' => self::words((string) $analyticsRow->subscription_status),
                'mrr' => '$'.number_format((float) $analyticsRow->mrr_current, 2),
                'interval' => $analyticsRow->billing_interval ?? null,
                'endsAt' => $analyticsRow->subscription_ends_at ?? null,
            ] : null,
            'billing' => [
                'mode' => $workspace->is_manually_billed ? 'manual' : ($workspace->is_unlimited ? 'unlimited' : 'lemon_squeezy'),
                'unitPrice' => $workspace->manual_billing_unit_price === null ? '' : (string) $workspace->manual_billing_unit_price,
                'ownerId' => $owner?->id,
                'globalUnitPrice' => $globalUnitPrice === null ? null : (string) $globalUnitPrice,
                'globalUnitPriceFormatted' => '$'.number_format((float) ($globalUnitPrice ?? 0), 2),
                'effectiveUnitPrice' => '$'.number_format($unitPrice, 2),
                'billableUnits' => $billableUnits,
                'unitsLabel' => Str::plural('unit', $billableUnits),
                'effectiveMrr' => '$'.number_format($unitPrice * $billableUnits, 2),
            ],
            'billingChanges' => $billingChanges->map(fn (BillingChange $change) => [
                'id' => $change->id,
                'when' => $change->detected_at?->format('d M Y H:i'),
                'increase' => $change->change_type === 'increase',
                'delta' => ($change->license_delta > 0 ? '+' : '').$change->license_delta,
                'units' => [$change->previous_license_count, $change->new_license_count],
                'displays' => [$change->previous_displays_count, $change->new_displays_count],
                'boards' => [$change->previous_boards_count, $change->new_boards_count],
                'mrr' => $change->mrr_delta === null
                    ? null
                    : ($change->mrr_delta > 0 ? '+' : '').'$'.number_format((float) $change->mrr_delta, 2),
            ])->values()->all(),
            'members' => $workspace->members->map(function (User $member) {
                $role = $member->pivot->role;

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => self::words((string) ($role instanceof \BackedEnum ? $role->value : $role)),
                    'url' => route('admin.users.show', $member),
                ];
            })->values()->all(),
            'urls' => [
                'index' => route('admin.workspaces.index'),
                'billing' => route('admin.workspaces.billing', $workspace),
            ],
        ]);
    }

    /**
     * Set how this workspace is billed.
     *
     * Three modes, exactly one at a time. Lemon Squeezy is the default. A manually billed
     * workspace is invoiced through our own accounting: it gets Pro without a subscription,
     * and its MRR is computed locally from usage at the unit price set here. An unlimited
     * workspace gets Pro for free (a non-profit deal, a test account) and is never charged.
     *
     * The two flags used to be set independently, and manual billing wins when both are on
     * (see Workspace::billingStatus()), so a free workspace marked manual quietly showed a
     * price and counted towards MRR. One choice keeps them apart.
     */
    public function updateBilling(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->checkAdminAccess();

        $validated = $request->validate([
            'billing_mode' => ['required', Rule::in(['lemon_squeezy', 'manual', 'unlimited'])],
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

        $mode = $validated['billing_mode'];

        $workspace->update([
            'is_manually_billed' => $mode === 'manual',
            'is_unlimited' => $mode === 'unlimited',
            'manual_billing_unit_price' => $price === '' ? null : $price,
            'billing_owner_user_id' => $owner ?? $workspace->billing_owner_user_id,
        ]);

        logger()->info('Admin updated workspace billing', [
            'workspace_id' => $workspace->id,
            'admin_id' => Auth::id(),
            'billing_mode' => $mode,
            'manual_billing_unit_price' => $workspace->manual_billing_unit_price,
            'billing_owner_user_id' => $workspace->billing_owner_user_id,
        ]);

        $this->refreshAnalyticsBilling($workspace);

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
     * Rewrite the stored analytics row right away, so the screen reflects the new billing
     * mode or unit price instead of the value from the last analytics refresh.
     *
     * A Lemon Squeezy workspace is left alone: the API is the only source for its MRR, so
     * the scheduled refresh re-derives the row.
     */
    private function refreshAnalyticsBilling(Workspace $workspace): void
    {
        if (! $workspace->is_manually_billed && ! $workspace->is_unlimited) {
            return;
        }

        $mrr = $workspace->is_manually_billed ? $workspace->calculateManualMrr() : 0;

        try {
            DB::table('analytics_workspaces')
                ->where('workspace_id', $workspace->id)
                ->update([
                    'subscription_status' => $workspace->billingStatus(),
                    'billing_interval' => $workspace->is_manually_billed ? 'monthly' : null,
                    'mrr_current' => $mrr,
                    'mrr_expected' => $mrr,
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            // Analytics table isn't present (e.g. self-hosted) — the scheduled refresh will catch up.
        }
    }

    /**
     * A stored value such as on_trial as sentence-case words: "On trial".
     */
    private static function words(string $value): string
    {
        return Str::ucfirst(Str::lower(Str::headline($value)));
    }

    /**
     * The plan of a workspace as a key the admin pages colour by and the word that goes with it.
     * Enterprise is Pro above 25 displays; a subscription in any other state shows that state.
     *
     * @return array{key: string, label: string}
     */
    public static function planBadge(Workspace $workspace, ?int $displays = null): array
    {
        $status = $workspace->billingStatus();
        $displays ??= $workspace->displays()->count();

        return match (true) {
            $status === 'none' => ['key' => 'free', 'label' => 'Free'],
            $status === 'manual' => ['key' => 'manual', 'label' => 'Manually billed'],
            $status === 'unlimited' => ['key' => 'unlimited', 'label' => 'Unlimited'],
            $status === 'on_trial' => ['key' => 'trial', 'label' => 'Pro trial'],
            $status === 'active' && $displays > self::ENTERPRISE_DISPLAYS => ['key' => 'enterprise', 'label' => 'Enterprise'],
            $status === 'active' => ['key' => 'pro', 'label' => 'Pro'],
            default => ['key' => 'attention', 'label' => self::words($status)],
        };
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
