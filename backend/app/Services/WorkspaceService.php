<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event as EventModel;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared workspace lifecycle operations.
 *
 * The ownership-transfer and teardown logic was previously duplicated verbatim in
 * ProfileController::destroy() and AdminController::deleteUser(), so the two paths could
 * drift apart. Both now delegate here.
 */
class WorkspaceService
{
    /**
     * Tables whose user_id is provenance ("created by") rather than ownership.
     *
     * @var array<int, class-string<Model>>
     */
    private const PROVENANCE_MODELS = [
        Display::class,
        Device::class,
        Calendar::class,
        Room::class,
        OutlookAccount::class,
        GoogleAccount::class,
        CalDAVAccount::class,
    ];

    /**
     * Hand a workspace to a new owner because the current one is leaving.
     *
     * Prefers the given successor, then any existing admin, then any remaining member.
     * Returns the new owner, or null when nobody is left to hand it to.
     */
    public function transferOwnershipAwayFrom(Workspace $workspace, User $leaving, ?User $successor = null): ?User
    {
        $others = $workspace->members()->where('users.id', '!=', $leaving->id)->get();

        if ($others->isEmpty()) {
            return null;
        }

        $newOwner = null;

        if ($successor) {
            $newOwner = $others->firstWhere('id', $successor->id);
        }

        $newOwner ??= $others->first(
            fn ($member) => WorkspaceRole::fromPivot($member->pivot->role) === WorkspaceRole::ADMIN
        ) ?? $others->first();

        WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $newOwner->id)
            ->update(['role' => WorkspaceRole::OWNER]);

        // The subscription stays attached to the workspace, so the departing owner's payment
        // method keeps funding it. Cancelling instead would take a working team's displays
        // offline, so this is surfaced rather than decided here. Needs a product answer: a
        // "billing needs a new payer" prompt for the new owner.
        if ($workspace->billing_owner_user_id === $leaving->id || $workspace->hasActiveSubscription()) {
            $workspace->update(['billing_owner_user_id' => $newOwner->id]);

            logger()->warning('Workspace ownership transferred while billed to the departing owner', [
                'workspace_id' => $workspace->id,
                'departing_user_id' => $leaving->id,
                'new_owner_user_id' => $newOwner->id,
                'has_active_subscription' => $workspace->hasActiveSubscription(),
            ]);
        }

        return $newOwner;
    }

    /**
     * Delete a workspace and everything inside it.
     *
     * Only call this for a workspace that is genuinely being retired — it destroys data
     * belonging to the workspace, not to any one user.
     */
    public function purge(Workspace $workspace): void
    {
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
        $workspace->displayProfiles()->delete();
        $workspace->boards()->delete();

        foreach ([$workspace->outlookAccounts, $workspace->googleAccounts, $workspace->caldavAccounts] as $accounts) {
            foreach ($accounts as $account) {
                $account->delete();
            }
        }

        WorkspaceMember::where('workspace_id', $workspace->id)->delete();

        // Local billing records only. This does not cancel anything at Lemon Squeezy — that
        // stays a deliberate manual step, as it always has been.
        if (method_exists($workspace, 'subscriptions')) {
            $workspace->subscriptions()->delete();
        }

        if (method_exists($workspace, 'customer')) {
            $workspace->customer()->delete();
        }

        $workspace->delete();
    }

    /**
     * Release a departing user's claim on data that stays behind in a shared workspace.
     *
     * The rows belong to the workspace; user_id only records who created them. Nulling it
     * keeps the team's displays, devices and calendar accounts intact when the user row
     * goes away.
     */
    public function releaseProvenance(Workspace $workspace, User $user): void
    {
        foreach (self::PROVENANCE_MODELS as $model) {
            $model::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->update(['user_id' => null]);
        }

        // Events hang off displays and calendars rather than a workspace, so reach them
        // through the displays that remain in this workspace.
        EventModel::whereIn('display_id', $workspace->displays()->select('displays.id'))
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);
    }

    /**
     * Delete data that belongs to a user but to no workspace.
     *
     * These rows are invisible to every workspace-scoped query and policy already (see the
     * `if (! $model->workspace_id) return false;` guards in the policies). Since user_id
     * now nulls out instead of cascading, they would otherwise linger unreachable forever
     * once the user is gone.
     */
    public function purgeOrphanedUserData(User $user): void
    {
        foreach ($user->displays()->whereNull('workspace_id')->get() as $display) {
            $display->eventSubscriptions()->delete();
            $display->settings()->delete();
            $display->events()->delete();
            $display->devices()->delete();
            $display->delete();
        }

        foreach ([OutlookAccount::class, GoogleAccount::class, CalDAVAccount::class] as $model) {
            $accounts = $model::where('user_id', $user->id)->whereNull('workspace_id')->get();

            foreach ($accounts as $account) {
                foreach ($account->calendars as $calendar) {
                    $calendar->events()->delete();
                    $calendar->delete();
                }
                $account->delete();
            }
        }

        foreach ([Device::class, Room::class, Calendar::class] as $model) {
            $model::where('user_id', $user->id)->whereNull('workspace_id')->delete();
        }
    }

    /**
     * Detach a user from every workspace, retiring the ones that would be left empty.
     *
     * This is the workspace half of deleting a user account: workspaces with other members
     * survive (ownership transferred, provenance released), workspaces without other
     * members are purged.
     */
    public function detachUserFromAllWorkspaces(User $user): void
    {
        foreach ($user->workspaces()->get() as $workspace) {
            $role = WorkspaceRole::fromPivot($workspace->pivot->role);

            $hasOtherMembers = $workspace->members()->where('users.id', '!=', $user->id)->exists();

            // Nobody left to hand it to — including the odd case of a workspace whose only
            // member is not its owner — so the workspace goes with the user.
            if (! $hasOtherMembers) {
                $this->purge($workspace);

                continue;
            }

            if ($role === WorkspaceRole::OWNER) {
                $this->transferOwnershipAwayFrom($workspace, $user);
            }

            $this->releaseProvenance($workspace, $user);
        }

        WorkspaceMember::where('user_id', $user->id)->delete();

        $this->purgeOrphanedUserData($user);
    }
}
