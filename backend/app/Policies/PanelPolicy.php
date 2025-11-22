<?php

namespace App\Policies;

use App\Models\Panel;
use App\Models\User;

class PanelPolicy
{
    /**
     * Determine if the user can view any panels.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the panel.
     */
    public function view(User $user, Panel $panel): bool
    {
        return $user->id === $panel->user_id;
    }

    /**
     * Determine if the user can create panels.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the panel.
     */
    public function update(User $user, Panel $panel): bool
    {
        return $user->id === $panel->user_id;
    }

    /**
     * Determine if the user can delete the panel.
     */
    public function delete(User $user, Panel $panel): bool
    {
        return $user->id === $panel->user_id;
    }
}

