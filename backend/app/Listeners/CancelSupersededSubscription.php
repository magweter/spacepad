<?php

namespace App\Listeners;

use App\Models\Workspace;
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

        $workspace = $event->billable;

        if (! $workspace instanceof Workspace) {
            return;
        }

        // A request to go monthly belongs to the yearly subscription it was made on; a new
        // one starts without it.
        $workspace->forceFill(['switch_to_monthly_requested_at' => null])->save();

        $superseded = $workspace->subscriptionsSupersededBy($event->subscription);

        foreach ($superseded as $subscription) {
            try {
                $subscription->cancel();
            } catch (Throwable $e) {
                // The webhook must still succeed: Lemon Squeezy would retry it and the
                // subscription row cannot be created twice. app:process-yearly-subscriptions
                // retries the cancellation every day until it goes through.
                report($e);
            }
        }
    }
}
