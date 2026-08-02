<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization for the workspace itself — its members, its name, its billing.
 *
 * Managing the *contents* of a workspace (displays, boards, profiles, calendar accounts)
 * is open to every member and lives in the respective content policies. This policy only
 * covers workspace administration.
 */
class WorkspacePolicy
{
    use HandlesAuthorization;

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    /**
     * Rename the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManage() ?? false;
    }

    /**
     * Invite a colleague.
     */
    public function invite(User $user, Workspace $workspace): bool
    {
        return ($this->role($user, $workspace)?->canInvite() ?? false)
            && $user->hasProForWorkspace($workspace);
    }

    /**
     * Withdraw a pending invitation. Deliberately not Pro-gated: an expired subscription
     * should never trap a workspace with invitations it cannot retract.
     */
    public function revokeInvitation(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canInvite() ?? false;
    }

    public function updateMemberRole(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManageMembers() ?? false;
    }

    public function removeMember(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManageMembers() ?? false;
    }

    public function manageBilling(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManageBilling() ?? false;
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    /**
     * Self-service deletion of a leftover empty workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->canBeDeletedBy($user);
    }

    /**
     * A user's role in the workspace, or null when they are not a member.
     */
    private function role(User $user, Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->getUserRole($user);
    }
}
