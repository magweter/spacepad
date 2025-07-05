<?php

namespace App\Listeners;

use App\Data\CalendarWebhookData;
use App\Data\DisplayWebhookData;
use App\Data\UserWebhookData;
use App\Events\UserOnboarded;
use Illuminate\Support\Facades\Http;

class SendOnboardingCompleteNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserOnboarded $event): void
    {
        $webhookUrl = config('settings.onboarding_complete_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'onboarding_complete',
            'user' => UserWebhookData::from($event->user),
            'display' => DisplayWebhookData::from($event->display),
            'calendar' => CalendarWebhookData::from($event->display->calendar),
        ]);
    }
}
