<?php

namespace App\Policies;

use App\Models\DisplayProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisplayProfilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create profiles.
     */
    public function create(User $user): bool
    {
        // User must have Pro and be a member of a workspace
        return $user->hasProForCurrentWorkspace() && $user->getSelectedWorkspace() !== null;
    }

    /**
     * Determine whether the user can view the profile.
     */
    public function view(User $user, DisplayProfile $profile): bool
    {
        return $profile->workspace && $profile->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can update the profile.
     */
    public function update(User $user, DisplayProfile $profile): bool
    {
        if (! $profile->workspace_id) {
            return false;
        }

        // Any member may manage the workspace's content. Managing the workspace itself
        // (members, billing) is owner/admin only — see WorkspacePolicy.
        $workspace = $profile->workspace;

        return $workspace && $workspace->hasMember($user);
    }

    /**
     * Determine whether the user can delete the profile.
     */
    public function delete(User $user, DisplayProfile $profile): bool
    {
        if (! $profile->workspace_id) {
            return false;
        }

        // Any member may manage the workspace's content. Managing the workspace itself
        // (members, billing) is owner/admin only — see WorkspacePolicy.
        $workspace = $profile->workspace;

        return $workspace && $workspace->hasMember($user);
    }
}
