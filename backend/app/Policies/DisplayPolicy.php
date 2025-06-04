<?php

namespace App\Policies;

use App\Models\Display;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisplayPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create displays.
     */
    public function create(User $user): bool
    {
        return $user->outlookAccounts()->count() > 0 || $user->googleAccounts()->count() > 0 || $user->caldavAccounts()->count() > 0;
    }

    /**
     * Determine whether the user can update the display.
     */
    public function update(User $user, Display $display): bool
    {
        return $user->id === $display->user_id;
    }

    /**
     * Determine whether the user can delete the display.
     */
    public function delete(User $user, Display $display): bool
    {
        return $user->id === $display->user_id;
    }
}
