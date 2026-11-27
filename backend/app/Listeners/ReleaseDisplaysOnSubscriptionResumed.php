<?php

namespace App\Listeners;

use App\Models\Workspace;
use App\Services\FreePlanDisplayLimit;
use LemonSqueezy\Laravel\Events\SubscriptionResumed;

/**
 * Displays that waited for Pro come back as soon as the workspace has it again.
 *
 * Reads the billable from the stored subscription rather than the event, so a legacy
 * subscription that RemapLegacySubscriptionBillable moves to its workspace still lands here.
 */
class ReleaseDisplaysOnSubscriptionResumed
{
    public function __construct(private FreePlanDisplayLimit $freePlan) {}

    public function handle(SubscriptionResumed $event): void
    {
        $billable = $event->subscription->fresh()?->billable ?? $event->billable;

        if ($billable instanceof Workspace) {
            $this->freePlan->release($billable->fresh());
        }
    }
}
