<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\UserPassive;
use Illuminate\Support\Facades\Http;

class SendUserPassiveNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserPassive $event): void
    {
        $webhookUrl = config('settings.user_passive_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'user_passive',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}

