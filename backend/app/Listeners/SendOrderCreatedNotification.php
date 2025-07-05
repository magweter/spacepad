<?php

namespace App\Listeners;

use App\Data\OrderWebhookData;
use App\Data\UserWebhookData;
use Illuminate\Support\Facades\Http;
use LemonSqueezy\Laravel\Events\OrderCreated;

class SendOrderCreatedNotification
{
    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        $webhookUrl = config('settings.order_created_webhook_url');
        if (!$webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'event' => 'order_created',
            'user' => UserWebhookData::from($event->billable),
            'order' => OrderWebhookData::from($event->order),
        ]);
    }
}
