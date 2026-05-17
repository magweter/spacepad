<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\DisplayCreatedNoDevice;
use Illuminate\Support\Facades\Http;

class SendDisplayCreatedNoDeviceNotification
{
    public function handle(DisplayCreatedNoDevice $event): void
    {
        $webhookUrl = config('settings.display_created_no_device_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        Http::timeout(5)->retry(2, 100)->post($webhookUrl, [
            'event' => 'display_created_no_device',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
