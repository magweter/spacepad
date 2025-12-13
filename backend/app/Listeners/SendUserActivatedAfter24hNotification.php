<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\UserActivatedAfter24h;
use Illuminate\Support\Facades\Http;

class SendUserActivatedAfter24hNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserActivatedAfter24h $event): void
    {
        $webhookUrl = config('settings.user_activated_after_24h_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'user_activated_after_24h',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}

