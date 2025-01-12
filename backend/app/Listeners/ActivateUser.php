<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Events\UserOnboarded;

class ActivateUser
{
    /**
     * Handle the event.
     */
    public function handle(UserOnboarded $event): void
    {
        $event->user->update(['status' => UserStatus::ACTIVE]);
    }
}
