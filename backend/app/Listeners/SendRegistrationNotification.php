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
        Notification::route('mail', 'support@outlooktogcal.com')
            ->notify(new RegistrationNotification($event->user));
    }
}
