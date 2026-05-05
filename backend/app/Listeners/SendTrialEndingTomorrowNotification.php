<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\TrialEndingTomorrow;
use Illuminate\Support\Facades\Http;

class SendTrialEndingTomorrowNotification
{
    public function handle(TrialEndingTomorrow $event): void
    {
        $webhookUrl = config('settings.trial_ending_tomorrow_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'trial_ending_tomorrow',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
