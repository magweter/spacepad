<?php

namespace App\Listeners;

use App\Data\UserWebhookData;
use App\Events\AccountConnectedNoDisplay;
use Illuminate\Support\Facades\Http;

class SendAccountConnectedNoDisplayNotification
{
    public function handle(AccountConnectedNoDisplay $event): void
    {
        $webhookUrl = config('settings.account_connected_no_display_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'account_connected_no_display',
            'user' => UserWebhookData::from($event->user),
        ]);
    }
}
