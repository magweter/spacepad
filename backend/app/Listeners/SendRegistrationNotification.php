<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\UserRegistered;
use Illuminate\Support\Facades\Http;

class SendRegistrationNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $webhookUrl = config('settings.registration_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'registration',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
