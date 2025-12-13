<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\UserInactive;
use Illuminate\Support\Facades\Http;

class SendUserInactiveNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserInactive $event): void
    {
        $webhookUrl = config('settings.user_inactive_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'user_inactive',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}

