<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create boards.
     */
    public function create(User $user): bool
    {
        // User must have Pro and be a member of a workspace
        return $user->hasProForCurrentWorkspace() && $user->getSelectedWorkspace() !== null;
    }

    /**
     * Determine whether the user can view the board.
     */
    public function view(User $user, Board $board): bool
    {
        // User must be a member of the workspace
        return $board->workspace && $board->workspace->hasMember($user);
    }

    /**
     * Determine whether the user can update the board.
     */
    public function update(User $user, Board $board): bool
    {
        if (! $board->workspace_id) {
            return false;
        }

        // Any member may manage the workspace's content. Managing the workspace itself
        // (members, billing) is owner/admin only — see WorkspacePolicy.
        $workspace = $board->workspace;

        return $workspace && $workspace->hasMember($user);
    }

    /**
     * Determine whether the user can delete the board.
     */
    public function delete(User $user, Board $board): bool
    {
        if (! $board->workspace_id) {
            return false;
        }

        // Any member may manage the workspace's content. Managing the workspace itself
        // (members, billing) is owner/admin only — see WorkspacePolicy.
        $workspace = $board->workspace;

        return $workspace && $workspace->hasMember($user);
    }
}
