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

        // The tiles above the tabs are filled by AdminStatsService through a view composer,
        // so they read the same here as on every other admin screen.
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

        // The snapshot is keyed on the workspace, because that is what holds a subscription.
        // A user is shown the state of the workspace they carry the billing for; a colleague
        // on someone else's plan has nothing of their own to surface.
        $billingWorkspace = AdminWorkspaceController::billingWorkspaceFor($user);

        try {
            $analyticsRow = $billingWorkspace
                ? DB::table('analytics_workspaces')->where('workspace_id', $billingWorkspace->id)->first()
                : null;
        } catch (\Exception $e) {
            $analyticsRow = null;
        }

        $subscriptionInfo = ($analyticsRow && $analyticsRow->subscription_status !== 'none') ? [
            'status' => $analyticsRow->subscription_status,
            'price' => $analyticsRow->mrr_current,
            'mrr' => $analyticsRow->mrr_current,
            'ends_at' => $analyticsRow->subscription_ends_at,
        ] : null;

        // Recent licence-count / MRR changes for the workspace they are billed under (empty
        // if the table isn't present, e.g. self-hosted).
        try {
            $billingChanges = $billingWorkspace
                ? BillingChange::where('workspace_id', $billingWorkspace->id)
                    ->orderByDesc('detected_at')
                    ->limit(20)
                    ->get()
                : collect();
        } catch (\Exception $e) {
            $billingChanges = collect();
        }

        return view('pages.admin.user', [
            'user' => $user,
            'subscriptionInfo' => $subscriptionInfo,
            'billingChanges' => $billingChanges,
            // Billing lives on the workspace; the form on this page edits that.
            'billingWorkspace' => $billingWorkspace,
        ]);
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

            // Scrub the user from the billing-change history rather than deleting the rows.
            // Those rows record what a *workspace* was charged for; the name and email are
            // only the contact at the time. Deleting them would tear holes in a shared
            // workspace's history because one colleague closed their account.
            BillingChange::where('user_id', $user->id)->update([
                'user_id' => null,
                'email' => null,
                'name' => null,
            ]);

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
