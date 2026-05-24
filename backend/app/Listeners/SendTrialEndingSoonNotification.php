<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\TrialEndingSoon;
use Illuminate\Support\Facades\Http;

class SendTrialEndingSoonNotification
{
    public function handle(TrialEndingSoon $event): void
    {
        $webhookUrl = config('settings.trial_ending_soon_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'trial_ending_soon',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
