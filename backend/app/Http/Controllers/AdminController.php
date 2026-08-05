<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\BillingChange;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    use ChecksAdminAccess;

    public function __construct(protected WorkspaceService $workspaces) {}

    public function index()
    {
        $this->checkAdminAccess();

        // Stats from the pre-computed analytics snapshot — gracefully returns zeros if not yet populated
        try {
            $userStats = DB::table('analytics_users')
                ->selectRaw('COUNT(CASE WHEN last_device_activity_at >= ? THEN 1 END) as active_users_count', [now()->subDays(7)])
                ->first();
        } catch (\Exception $e) {
            $userStats = null;
        }

        try {
            $instanceStats = DB::table('analytics_instances')
                ->selectRaw('COUNT(*) as total_instances, COUNT(CASE WHEN last_heartbeat_at >= ? THEN 1 END) as active_instances_count', [now()->subDays(7)])
                ->first();
        } catch (\Exception $e) {
            $instanceStats = null;
        }

        $search = request()->get('search');
        // Pro is a property of the workspace now, so the Pro column reads the user's owned
        // workspaces rather than a subscription hanging off the user.
        $allUsersQuery = User::query()
            ->withCount('displays')
            ->withCount('boards')
            ->with(['ownedWorkspaces' => function ($query) {
                $query->with(['subscriptions' => function ($subQuery) {
                    $subQuery->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
                }]);
            }]);

        if ($search) {
            $allUsersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $allUsers = $allUsersQuery
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $roadmapItems = RoadmapItem::withCount('votes')
            ->with('submittedBy')
            ->orderBy('is_approved')
            ->ordered()
            ->get();

        return view('pages.admin', [
            'allUsers' => $allUsers,
            'activeUsersCount' => $userStats->active_users_count ?? 0,
            'totalInstances' => $instanceStats->total_instances ?? 0,
            'activeInstancesCount' => $instanceStats->active_instances_count ?? 0,
            'roadmapItems' => $roadmapItems,
        ]);
    }

    /**
     * Show user details page
     */
    public function showUser(User $user)
    {
        $this->checkAdminAccess();

        $user->load([
            'outlookAccounts',
            'googleAccounts',
            'caldavAccounts',
            'displays',
            'boards',
            'devices',
            'workspaces',
            'subscriptions' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })->orderByDesc('created_at');
            },
        ]);

        // RefreshAnalytics writes a row per membership, and only the row of the member who
        // carries the billing holds the subscription. Anyone else gets "member" (a colleague
        // on someone else's plan) or "none" (nothing to bill) — neither is worth surfacing.
        $analyticsRow = DB::table('analytics_users')
            ->where('user_id', $user->id)
            ->orderByDesc('is_billing_owner')
            ->first();
        $subscriptionInfo = ($analyticsRow && ! in_array($analyticsRow->subscription_status, ['none', 'member'], true)) ? [
            'status' => $analyticsRow->subscription_status,
            'price' => $analyticsRow->mrr_current,
            'mrr' => $analyticsRow->mrr_current,
            'ends_at' => $analyticsRow->subscription_ends_at,
        ] : null;

        // Recent license-count / MRR changes (empty if the table isn't present, e.g. self-hosted)
        try {
            $billingChanges = BillingChange::where('user_id', $user->id)
                ->orderByDesc('detected_at')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            $billingChanges = collect();
        }

        return view('pages.admin.user', [
            'user' => $user,
            'subscriptionInfo' => $subscriptionInfo,
            'billingChanges' => $billingChanges,
            // Billing lives on the workspace; the form on this page edits that.
            'billingWorkspace' => $this->billingWorkspaceFor($user),
        ]);
    }

    /**
     * Update manual billing for a user's billing workspace.
     *
     * Manually-billed accounts are invoiced through our own accounting system instead of
     * Lemon Squeezy. They receive Pro without an LS subscription, and their MRR is computed
     * locally from usage (see RefreshAnalytics) rather than fetched from LS.
     *
     * The flags live on the workspace now, since that is what usage is measured against.
     * This route stays addressed by user so existing links keep working; it applies to that
     * user's billing workspace.
     */
    public function updateBilling(Request $request, User $user): RedirectResponse
    {
        $this->checkAdminAccess();

        $validated = $request->validate([
            'manual_billing_unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $workspace = $this->billingWorkspaceFor($user);

        if (! $workspace) {
            return back()->with('error', 'This user has no workspace to bill.');
        }

        $price = $validated['manual_billing_unit_price'] ?? null;

        $workspace->update([
            'is_manually_billed' => $request->boolean('is_manually_billed'),
            'manual_billing_unit_price' => $price === '' ? null : $price,
            'billing_owner_user_id' => $workspace->billing_owner_user_id ?? $user->id,
        ]);

        logger()->info('Admin updated manual billing', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'is_manually_billed' => $workspace->is_manually_billed,
            'manual_billing_unit_price' => $workspace->manual_billing_unit_price,
        ]);

        $this->refreshManualMrr($workspace);

        return back()->with('success', 'Billing settings updated.');
    }

    /**
     * The workspace that carries a user's billing.
     */
    private function billingWorkspaceFor(User $user): ?Workspace
    {
        return Workspace::where('billing_owner_user_id', $user->id)->orderBy('id')->first()
            ?? $user->ownedWorkspaces()->orderByPivot('created_at')->first()
            ?? $user->primaryWorkspace();
    }

    /**
     * Recompute the stored MRR right away, so the admin screen reflects a new unit price
     * instead of the value from the last analytics refresh.
     *
     * Only while manually billed — once the flag is off, Lemon Squeezy is the only source
     * for MRR, so the row is left for the scheduled refresh to re-derive.
     */
    private function refreshManualMrr(Workspace $workspace): void
    {
        if (! $workspace->is_manually_billed) {
            return;
        }

        try {
            $mrr = $workspace->calculateManualMrr(
                $workspace->displays()->count(),
                $workspace->boards()->count(),
            );

            // Target the workspace's billing row only. Updating every row this user appears
            // on would repeat the same MRR across each of their memberships, which is exactly
            // the double count the snapshot is built to avoid.
            DB::table('analytics_users')
                ->where('workspace_id', $workspace->id)
                ->where('is_billing_owner', true)
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
     * Delete a user account and all associated data
     */
    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        $this->checkAdminAccess();

        $admin = Auth::user();

        // Prevent deleting yourself
        if ($user->id === $admin->id) {
            return redirect()->route('admin.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Confirm deletion
        $request->validate([
            'confirm_email' => ['required', 'email'],
        ]);

        if ($request->input('confirm_email') !== $user->email) {
            return back()->withErrors(['confirm_email' => 'Email confirmation does not match.']);
        }

        DB::transaction(function () use ($user, $admin) {
            // Delete all user's personal access tokens
            $user->tokens()->delete();

            // Deliberately workspace-first: data lives in a workspace, and user_id only
            // records who created it. Deleting by $user->displays would take a shared
            // workspace's displays down with a single departing colleague. Workspaces with
            // other members survive (ownership transferred, provenance released); those
            // without are purged, along with any data that belongs to no workspace.
            $this->workspaces->detachUserFromAllWorkspaces($user);

            // Note: Instances are system-wide (for self-hosted tracking), not user-specific
            // No need to delete instances when deleting a user

            // Cancel LemonSqueezy subscriptions (if any)
            // Note: This doesn't actually cancel them in LemonSqueezy, just removes the local reference
            // You might want to add API call to cancel subscriptions
            if (method_exists($user, 'subscriptions')) {
                $user->subscriptions()->delete();
            }

            // Remove the user's billing-change history — those rows keep a denormalized
            // copy of the user's email and name, so they must not outlive the account.
            BillingChange::where('user_id', $user->id)->delete();

            // Finally, delete the user
            $user->delete();

            logger()->info('User account deleted by admin', [
                'deleted_user_id' => $user->id,
                'deleted_by_admin_id' => $admin->id,
            ]);
        });

        return redirect()->route('admin.index')
            ->with('success', "User account {$user->email} and all associated data have been permanently deleted.");
    }

    /**
     * Impersonate a user
     */
    public function impersonate(User $user): RedirectResponse
    {
        $this->checkAdminAccess();

        $admin = Auth::user();

        // Prevent impersonating yourself
        if ($admin->id === $user->id) {
            return redirect()->route('admin.index')
                ->with('error', 'You cannot impersonate yourself.');
        }

        // Store original admin ID in session
        session()->put('impersonating', true);
        session()->put('impersonator_id', $admin->id);

        // Clear any workspace selection from admin session - let impersonated user's workspace be selected
        session()->forget('selected_workspace_id');

        // Log in as the target user
        Auth::login($user);

        // Regenerate session and CSRF token to prevent session fixation
        session()->regenerate();
        session()->regenerateToken();

        logger()->info('Admin started impersonating user', [
            'admin_id' => $admin->id,
            'impersonated_user_id' => $user->id,
        ]);

        return redirect()->route('dashboard')
            ->with('success', "You are now impersonating {$user->email}");
    }

    /**
     * Stop impersonating and return to admin account
     */
    public function stopImpersonating(): RedirectResponse
    {
        $impersonatorId = session()->get('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonator = User::find($impersonatorId);
        if (! $impersonator || ! $impersonator->isAdmin()) {
            session()->forget(['impersonating', 'impersonator_id']);

            return redirect()->route('dashboard');
        }

        $impersonatedUser = Auth::user();

        // Clear impersonation session
        session()->forget(['impersonating', 'impersonator_id']);

        // Log back in as admin
        Auth::login($impersonator);

        // Regenerate session and CSRF token to prevent session fixation
        session()->regenerate();
        session()->regenerateToken();

        logger()->info('Admin stopped impersonating user', [
            'admin_id' => $impersonator->id,
            'admin_email' => $impersonator->email,
            'impersonated_user_id' => $impersonatedUser->id,
            'impersonated_user_email' => $impersonatedUser->email,
        ]);

        return redirect()->route('admin.index')
            ->with('success', 'Stopped impersonating user.');
    }
}
