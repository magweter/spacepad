<?php

namespace App\Listeners;

use App\Models\Workspace;
use App\Services\FreePlanDisplayLimit;
use LemonSqueezy\Laravel\Events\SubscriptionExpired;

/**
 * A subscription that has definitively ended puts the workspace back on the free plan.
 *
 * Only on expiry: a cancelled subscription keeps Pro until its end date, and a failed
 * payment is retried by Lemon Squeezy, so neither changes anything on its own.
 */
class ApplyFreePlanWhenSubscriptionExpires
{
    public function __construct(private FreePlanDisplayLimit $freePlan) {}

    public function handle(SubscriptionExpired $event): void
    {
        $billable = $event->subscription->fresh()?->billable ?? $event->billable;

        if ($billable instanceof Workspace) {
            $this->freePlan->apply($billable);
        }
    }
}
