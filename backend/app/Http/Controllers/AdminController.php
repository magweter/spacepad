<?php

namespace App\Http\Controllers;

use App\Models\RoadmapItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Check if the current request is authorized for admin access
     */
    private function checkAdminAccess(): void
    {
        $user = Auth::user();

        // Prevent access if impersonating
        if (session()->get('impersonating')) {
            abort(403, 'Cannot access admin panel while impersonating. Please stop impersonating first.');
        }

        // Check if current user is admin
        if (! $user || ! $user->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }
    }

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
        $allUsersQuery = User::query()
            ->withCount('displays')
            ->withCount('boards')
            ->with(['subscriptions' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                });
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
            'devices',
            'workspaces',
            'subscriptions' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })->orderByDesc('created_at');
            },
        ]);

        // RefreshAnalytics writes a row for every user with a default status of "none",
        // so only surface subscription info when the user actually has a subscription.
        $analyticsRow = DB::table('analytics_users')->where('user_id', $user->id)->first();
        $subscriptionInfo = ($analyticsRow && $analyticsRow->subscription_status !== 'none') ? [
            'status' => $analyticsRow->subscription_status,
            'price' => $analyticsRow->mrr_current,
            'mrr' => $analyticsRow->mrr_current,
            'ends_at' => $analyticsRow->subscription_ends_at,
        ] : null;

        // Recent license-count / MRR changes (empty if the table isn't present, e.g. self-hosted)
        try {
            $billingChanges = \App\Models\BillingChange::where('user_id', $user->id)
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
        ]);
    }

    /**
     * Update a user's manual billing setting.
     *
     * Manually-billed users are invoiced through our own accounting system instead of
     * Lemon Squeezy. They receive Pro access without an LS subscription, and their MRR is
     * computed locally from usage (see RefreshAnalytics) rather than fetched from LS.
     */
    public function updateBilling(Request $request, User $user): RedirectResponse
    {
        $this->checkAdminAccess();

        $user->update([
            'is_manually_billed' => $request->boolean('is_manually_billed'),
        ]);

        logger()->info('Admin updated manual billing', [
            'user_id' => $user->id,
            'admin_id' => Auth::id(),
            'is_manually_billed' => $user->is_manually_billed,
        ]);

        return back()->with('success', 'Billing settings updated.');
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

            // Delete displays and their related data first (before calendars/accounts)
            if ($user->displays) {
                foreach ($user->displays as $display) {
                    // Delete event subscriptions
                    $display->eventSubscriptions()->delete();
                    // Delete display settings
                    $display->settings()->delete();
                    // Delete events associated with this display
                    $display->events()->delete();
                    // Delete devices associated with this display
                    $display->devices()->delete();
                    $display->delete();
                }
            }

            // Delete devices (standalone devices not linked to displays)
            $user->devices()->delete();

            // Delete rooms
            $user->rooms()->delete();

            // Delete Outlook accounts and their calendars/events
            if ($user->outlookAccounts) {
                foreach ($user->outlookAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete Google accounts and their calendars/events
            if ($user->googleAccounts) {
                foreach ($user->googleAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete CalDAV accounts and their calendars/events
            if ($user->caldavAccounts) {
                foreach ($user->caldavAccounts as $account) {
                    if ($account->calendars) {
                        foreach ($account->calendars as $calendar) {
                            $calendar->events()->delete();
                            $calendar->delete();
                        }
                    }
                    $account->delete();
                }
            }

            // Delete any remaining calendars directly linked to user (shouldn't happen, but safety check)
            // Note: Calendars are linked through accounts, not directly to users, so this is unlikely
            // Events are deleted through calendars above

            // Handle workspaces
            $ownedWorkspaces = $user->ownedWorkspaces()->get();
            foreach ($ownedWorkspaces as $workspace) {
                // Get other members (excluding the user being deleted)
                $otherMembers = $workspace->members()->where('user_id', '!=', $user->id)->get();

                if ($otherMembers->isNotEmpty()) {
                    // Find first admin or first member to transfer ownership
                    $newOwner = $otherMembers->first(function ($member) {
                        return $member->pivot->role === \App\Enums\WorkspaceRole::ADMIN->value;
                    }) ?? $otherMembers->first();

                    if ($newOwner) {
                        // Transfer ownership
                        WorkspaceMember::where('workspace_id', $workspace->id)
                            ->where('user_id', $newOwner->id)
                            ->update(['role' => \App\Enums\WorkspaceRole::OWNER]);
                    }
                } else {
                    // No other members, delete the workspace and all its data
                    foreach ($workspace->displays as $display) {
                        $display->eventSubscriptions()->delete();
                        $display->settings()->delete();
                        $display->events()->delete();
                        $display->devices()->delete();
                        $display->delete();
                    }
                    $workspace->devices()->delete();
                    foreach ($workspace->calendars as $calendar) {
                        $calendar->events()->delete();
                        $calendar->delete();
                    }
                    $workspace->rooms()->delete();
                    WorkspaceMember::where('workspace_id', $workspace->id)->delete();
                    $workspace->delete();
                }
            }

            // Delete workspace memberships (user's membership in workspaces they don't own)
            WorkspaceMember::where('user_id', $user->id)->delete();

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
            \App\Models\BillingChange::where('user_id', $user->id)->delete();

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
