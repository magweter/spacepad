<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\TrialDayThree;
use Illuminate\Support\Facades\Http;

class SendTrialDayThreeNotification
{
    public function handle(TrialDayThree $event): void
    {
        $webhookUrl = config('settings.trial_day_three_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'trial_day_three',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
