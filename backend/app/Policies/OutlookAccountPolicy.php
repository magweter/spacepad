<?php

namespace App\Policies;

use App\Models\OutlookAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutlookAccountPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the account.
     */
    public function view(User $user, OutlookAccount $account): bool
    {
        return $this->belongsToUser($user, $account);
    }

    /**
     * Determine whether the user can update the account.
     */
    public function update(User $user, OutlookAccount $account): bool
    {
        return $this->belongsToUser($user, $account);
    }

    /**
     * Determine whether the user can delete the account.
     */
    public function delete(User $user, OutlookAccount $account): bool
    {
        return $this->belongsToUser($user, $account);
    }

    /**
     * Access is granted through workspace membership, so any member of the
     * workspace can manage its calendar accounts. Legacy rows that were never
     * stamped with a workspace fall back to their creator.
     */
    private function belongsToUser(User $user, OutlookAccount $account): bool
    {
        if ($account->workspace_id) {
            return $account->workspace?->hasMember($user) ?? false;
        }

        return $account->user_id === $user->id;
    }
}
