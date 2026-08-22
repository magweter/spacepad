<?php

namespace App\Listeners;

use App\Data\OrderWebhookData;
use App\Data\UserWebhookData;
use App\Models\User;
use App\Models\Workspace;
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
        if (! $webhookUrl) {
            return;
        }

        // The billable is a Workspace now, but the outgoing payload describes a person, so
        // resolve the workspace's billing owner. Legacy orders whose checkout recorded a
        // User as the billable still arrive that way.
        $user = $event->billable instanceof Workspace
            ? $event->billable->billingOwner()
            : $event->billable;

        if (! $user instanceof User) {
            logger()->warning('Order created without a resolvable user for the webhook payload', [
                'billable_type' => $event->billable::class,
                'billable_id' => $event->billable->getKey(),
            ]);

            return;
        }

        Http::post($webhookUrl, [
            'event' => 'order_created',
            'user' => UserWebhookData::from($user),
            'order' => OrderWebhookData::from($event->order),
        ]);
    }
}
