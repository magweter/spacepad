<?php

namespace App\Listeners;

use App\Services\BillingPlans;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;
use LemonSqueezy\Laravel\Subscription;
use Throwable;

/**
 * Cancel the monthly subscription a new yearly one replaces.
 *
 * Switching to yearly is a fresh checkout, so for a moment the workspace has two
 * subscriptions. Cancelling at Lemon Squeezy stops the monthly one from renewing; it runs
 * out at the end of the month already paid for, with no refund, and the yearly one (the
 * newest) is what liveSubscription() reads from now on.
 *
 * Runs only after a paid checkout, because only then does this webhook arrive: an
 * abandoned checkout leaves the monthly subscription untouched.
 */
class CancelSupersededSubscription
{
    public function handle(SubscriptionCreated $event): void
    {
        if (! BillingPlans::isYearly($event->subscription)) {
            return;
        }

        $superseded = $event->billable->subscriptions()
            ->whereKeyNot($event->subscription->getKey())
            ->whereNotIn('status', [Subscription::STATUS_CANCELLED, Subscription::STATUS_EXPIRED])
            ->whereNull('ends_at')
            ->get();

        foreach ($superseded as $subscription) {
            try {
                $subscription->cancel();
            } catch (Throwable $e) {
                // The webhook must still succeed, or Lemon Squeezy retries it and the
                // subscription row is created twice. The monthly one is then cancelled by
                // hand; the report says which.
                report($e);
            }
        }
    }
}
