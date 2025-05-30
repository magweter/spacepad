<?php

namespace App\Listeners;

use App\Events\UserOnboarded;
use App\Notifications\OnboardingCompleteNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Http;

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
        
        $webhookUrl = config('settings.onboarding_complete_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        // Example JSON payload:
        // {
        //     "user_id": 123,
        //     "email": "john.doe@example.com",
        //     "name": "John Doe",
        //     "display": "Office Display",
        //     "event": "onboarding_complete"
        // }
        Http::post($webhookUrl, [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'name' => $event->user->name,
            'display' => $event->display->name,
            'event' => 'onboarding_complete',
        ]);
    }
}
