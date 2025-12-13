<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\TrialExpiredOrCancelled;
use Illuminate\Support\Facades\Http;

class SendTrialExpiredOrCancelledNotification
{
    /**
     * Handle the event.
     */
    public function handle(TrialExpiredOrCancelled $event): void
    {
        $webhookUrl = config('settings.trial_expired_or_cancelled_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'trial_expired_or_cancelled',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}

