<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\BillingChange;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use App\Support\AdminPage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    use ChecksAdminAccess;

    public function __construct(protected WorkspaceService $workspaces) {}

    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        $this->checkAdminAccess();

        // The roadmap used to be a pane of this page; old links land on its own screen now.
        if ($request->query('tab') === 'roadmap') {
            return redirect()->route('admin.roadmap.index');
        }

        // The figures above the tabs come from AdminStatsService (AdminPage::props), so they
        // read the same here as on every other admin screen.
        $search = $request->get('search');
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

        return Inertia::render('Admin/Users', [
            ...AdminPage::props($request),
            'search' => (string) $search,
            'users' => $allUsers->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'usageType' => $user->usage_type?->label(),
                'displays' => (int) $user->displays_count,
                'boards' => (int) ($user->boards_count ?? 0),
                'plan' => $this->bestPlan($user),
                'registered' => $user->created_at->format('Y-m-d'),
                'lastActivity' => $user->last_activity_at?->format('Y-m-d'),
                'urls' => [
                    'show' => route('admin.users.show', $user),
                    'impersonate' => route('admin.users.impersonate', $user),
                ],
            ])->values(),
            'pagination' => [
                'currentPage' => $allUsers->currentPage(),
                'lastPage' => $allUsers->lastPage(),
                'total' => $allUsers->total(),
                'from' => $allUsers->firstItem(),
                'to' => $allUsers->lastItem(),
                'prev' => $allUsers->previousPageUrl(),
                'next' => $allUsers->nextPageUrl(),
                // The numbered links with the gaps, as Laravel's own pagination view draws them.
                'links' => collect($allUsers->linkCollection())
                    ->slice(1, -1)
                    ->map(fn ($link) => ['url' => $link['url'], 'label' => $link['label'], 'active' => $link['active']])
                    ->values(),
            ],
            'urls' => ['index' => route('admin.index')],
        ]);
    }

    /**
     * Show user details page
     */
    public function showUser(Request $request, User $user): InertiaResponse
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

        $units = $billingWorkspace?->getTotalUsageCount();

        return Inertia::render('Admin/User', [
            // A drill-down: the shell only, without the tabs and figures of the overviews.
            'shell' => AdminPage::props($request)['shell'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'usageType' => $user->usage_type?->label(),
                'created' => $user->created_at->format('Y-m-d H:i:s'),
                'lastActivity' => $user->last_activity_at?->format('Y-m-d H:i:s'),
                'isSelf' => $user->is(Auth::user()),
            ],
            'counts' => [
                'outlookAccounts' => $user->outlookAccounts->count(),
                'googleAccounts' => $user->googleAccounts->count(),
                'caldavAccounts' => $user->caldavAccounts->count(),
                'displays' => $user->displays->count(),
                'devices' => $user->devices->count(),
                'workspaces' => $user->workspaces->count(),
            ],
            // Shown when the billing workspace has Pro or the analytics snapshot has a subscription.
            'subscription' => ($billingWorkspace?->hasPro() || $subscriptionInfo) ? [
                'plan' => $billingWorkspace
                    ? AdminWorkspaceController::planBadge($billingWorkspace)
                    : ['key' => $subscriptionInfo ? 'pro' : 'free', 'label' => $subscriptionInfo ? 'Pro' : 'Free'],
                'info' => $subscriptionInfo ? [
                    'status' => $subscriptionInfo['status'],
                    'statusLabel' => Str::ucfirst(str_replace('_', ' ', $subscriptionInfo['status'])),
                    'price' => number_format((float) $subscriptionInfo['price'], 2),
                    'mrr' => number_format((float) $subscriptionInfo['mrr'], 2),
                    'endsAt' => $subscriptionInfo['ends_at'] ? Carbon::parse($subscriptionInfo['ends_at'])->format('Y-m-d') : null,
                ] : null,
            ] : null,
            'billingChanges' => $billingChanges->map(fn (BillingChange $change) => [
                'id' => $change->id,
                'detectedAt' => $change->detected_at->format('Y-m-d H:i'),
                'increase' => $change->change_type === 'increase',
                'delta' => $change->license_delta,
                'licenses' => [$change->previous_license_count, $change->new_license_count],
                'displays' => [$change->previous_displays_count, $change->new_displays_count],
                'boards' => [$change->previous_boards_count, $change->new_boards_count],
                'mrr' => [number_format((float) $change->previous_mrr, 2), number_format((float) $change->new_mrr, 2)],
            ])->values(),
            // Billing lives on the workspace; its own admin page edits that.
            'billingWorkspace' => $billingWorkspace ? [
                'name' => $billingWorkspace->name,
                'units' => $units,
                'unitsLabel' => Str::plural('unit', $units),
                'displays' => (int) $billingWorkspace->displays_count,
                'boards' => (int) $billingWorkspace->boards_count,
                'url' => route('admin.workspaces.show', $billingWorkspace),
            ] : null,
            'urls' => [
                'back' => route('admin.index'),
                'delete' => route('admin.users.delete', $user),
                'impersonate' => route('admin.users.impersonate', $user),
            ],
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
    public function impersonate(User $user): Response
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

        // A full page load, not an Inertia visit: the impersonation banner is part of the root
        // view, which an Inertia visit does not draw again.
        session()->flash('success', "You are now impersonating {$user->email}");

        return Inertia::location(route('dashboard'));
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

    /**
     * The best plan among the workspaces a user owns, as the workspaces list labels it (Manually
     * billed, Unlimited, Enterprise, Pro, Pro trial or Free), so the users list tells them apart.
     *
     * @return array{key: string, label: string}
     */
    private function bestPlan(User $user): array
    {
        $rank = ['enterprise' => 6, 'unlimited' => 5, 'manual' => 4, 'pro' => 3, 'trial' => 2, 'free' => 0];

        return $user->ownedWorkspaces
            ->map(fn ($workspace) => AdminWorkspaceController::planBadge($workspace))
            ->sortByDesc(fn (array $plan) => $rank[$plan['key']] ?? 1)
            ->first() ?? ['key' => 'free', 'label' => 'Free'];
    }
}
