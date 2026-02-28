<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\UserNotActivatedAfter24h;
use Illuminate\Support\Facades\Http;

class SendUserNotActivatedAfter24hNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserNotActivatedAfter24h $event): void
    {
        $webhookUrl = config('settings.user_not_activated_after_24h_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'user_not_activated_after_24h',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}

