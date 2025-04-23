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
        Notification::route('mail', 'martijn@magweter.com')
            ->notify(new OnboardingCompleteNotification($event->user, $event->display));
    }
}
