<?php

namespace App\Listeners;

use App\Models\Workspace;
use LemonSqueezy\Laravel\Events\SubscriptionResumed;

/**
 * Someone who takes their cancellation back is not asked why they cancelled.
 */
class ClearCancellationFeedbackOnResume
{
    public function handle(SubscriptionResumed $event): void
    {
        $billable = $event->subscription->fresh()?->billable ?? $event->billable;

        if ($billable instanceof Workspace) {
            $billable->cancellations()->whereNull('reason')->delete();
        }
    }
}
