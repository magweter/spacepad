<?php

namespace App\Listeners;

use App\Events\UserOnboarded;
use App\Notifications\OnboardingCompleteNotification;
use Illuminate\Support\Facades\Notification;

class SendOnboardingCompleteNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserOnboarded $event): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        Notification::route('mail', 'support@spacepad.it')
            ->notify(new OnboardingCompleteNotification($event->user, $event->display));
    }
}
