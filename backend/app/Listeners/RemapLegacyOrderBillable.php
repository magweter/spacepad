<?php

namespace App\Listeners;

use App\Listeners\Concerns\RemapsLegacyBillable;
use LemonSqueezy\Laravel\Events\OrderCreated;

/**
 * Renewal invoices fire OrderCreated with the billable recorded at checkout time, so
 * pre-cutover subscriptions keep arriving addressed to a user.
 */
class RemapLegacyOrderBillable
{
    use RemapsLegacyBillable;

    public function handle(OrderCreated $event): void
    {
        $this->remap($event->order, $event->billable);
    }
}
