<?php

namespace App\Listeners;

use App\Enums\FunnelStep;
use App\Models\Workspace;
use App\Services\Funnel;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;

/**
 * The last step of the funnel: a subscription, trial or paid, which the status tells apart.
 */
class RecordSubscriptionInFunnel
{
    public function handle(SubscriptionCreated $event): void
    {
        $billable = $event->subscription->fresh()?->billable ?? $event->billable;

        if ($billable instanceof Workspace) {
            Funnel::recordOnce(FunnelStep::SUBSCRIBED, $billable, null, ['status' => $event->subscription->status]);
        }
    }
}
