<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\RegistrationNotification;
use Illuminate\Support\Facades\Notification;

class SendRegistrationNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Notification::route('mail', 'support@spacepad.it')
            ->notify(new RegistrationNotification($event->user));
    }
}
