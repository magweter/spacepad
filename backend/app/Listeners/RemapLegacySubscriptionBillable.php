<?php

namespace App\Listeners;

use App\Listeners\Concerns\RemapsLegacyBillable;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;

/**
 * A separate class per event rather than one union-typed handler, because Laravel's event
 * auto-discovery keys off the single type-hinted parameter.
 */
class RemapLegacySubscriptionBillable
{
    use RemapsLegacyBillable;

    public function handle(SubscriptionCreated $event): void
    {
        $this->remap($event->subscription, $event->billable);
    }
}
