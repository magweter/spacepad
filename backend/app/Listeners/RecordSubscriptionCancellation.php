<?php

namespace App\Listeners;

use App\Models\SubscriptionCancellation;
use App\Models\Workspace;
use LemonSqueezy\Laravel\Events\SubscriptionCancelled;

/**
 * A cancellation made outside the app (the billing portal, or the link in a Lemon Squeezy
 * email) gets a row without a reason, so the dashboard can ask for it on the next visit.
 *
 * A cancellation from the app already has its row by the time this webhook arrives.
 */
class RecordSubscriptionCancellation
{
    public function handle(SubscriptionCancelled $event): void
    {
        $billable = $event->subscription->fresh()?->billable ?? $event->billable;

        if (! $billable instanceof Workspace) {
            return;
        }

        $alreadyRecorded = SubscriptionCancellation::query()
            ->where('workspace_id', $billable->id)
            ->where('lemon_squeezy_subscription_id', $event->subscription->lemon_squeezy_id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        SubscriptionCancellation::create([
            'workspace_id' => $billable->id,
            'lemon_squeezy_subscription_id' => $event->subscription->lemon_squeezy_id,
            'source' => SubscriptionCancellation::SOURCE_PORTAL,
        ]);
    }
}
