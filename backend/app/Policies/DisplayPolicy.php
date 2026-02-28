<?php

namespace App\Policies;

use App\Models\Display;
use App\Models\User;
use App\Models\Device;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisplayPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create displays.
     */
    public function create(User $user): bool
    {
        return $user->isOnboarded();
    }

    /**
     * Determine whether the user can update the display.
     */
    public function update(User $user, Display $display): bool
    {
        if (!$display->workspace_id) {
            return false;
        }

        $workspace = $display->workspace;
        return $workspace && $workspace->canBeManagedBy($user);
    }

    /**
     * Determine whether the user can delete the display.
     */
    public function delete(User $user, Display $display): bool
    {
        if (!$display->workspace_id) {
            return false;
        }

        $workspace = $display->workspace;
        return $workspace && $workspace->canBeManagedBy($user);
    }

    /**
     * Determine whether the user can view the display.
     */
    public function view($user, Display $display): bool
    {
        // Handle User model
        if ($user instanceof User) {
            if (!$display->workspace_id) {
                return false;
            }

            $workspace = $display->workspace;
            return $workspace && $workspace->hasMember($user);
        }
        
        // Handle Device model
        if ($user instanceof Device) {
            return $user->display_id === $display->id;
        }
        
        return false;
    }
}
