<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\BillingPlans;
use App\Services\LemonSqueezyUsageService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use LemonSqueezy\Laravel\Subscription;
use RuntimeException;
use Throwable;

/**
 * Bill yearly subscriptions for what they use.
 *
 * A yearly subscription is paid a year ahead for the usage at that moment, and the usage
 * push leaves its quantity alone. This takes over, once a day:
 *
 *  - On the monthly anniversary (the day of the month the year started), usage above the
 *    paid quantity is invoiced straight away, prorated for the rest of the year. Lemon
 *    Squeezy only bills on a quantity change, which is why the push must not raise it first.
 *    Lower usage is never credited.
 *  - In the days before renewal, the quantity is set to current usage without proration, so
 *    the renewal bills the new year for what is used then. A requested switch to monthly is
 *    made on the last day of the paid year: Lemon Squeezy starts the monthly cycle then.
 *  - A monthly subscription the yearly one replaced but that is still running is cancelled.
 */
class ProcessYearlySubscriptions extends Command
{
    protected $signature = 'app:process-yearly-subscriptions {--dry-run : Show what would happen without changing billing}';

    protected $description = 'Invoice extra usage on yearly subscriptions and prepare their renewal';

    /**
     * Days before renewal in which the renewal quantity is set, every day, so a refused call
     * or a day the scheduler did not run is made good the next day. Setting it again is
     * harmless. A switch to monthly is only made on the last of these days.
     */
    private const RENEWAL_WINDOW_DAYS = 3;

    public function __construct(private readonly LemonSqueezyUsageService $usage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('settings.is_self_hosted') || ! $this->usage->hasApiKey()) {
            $this->info('Skipping: self-hosted, or no Lemon Squeezy API key configured.');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $errors = 0;
        $handled = 0;

        $this->workspaces()->chunkById(200, function ($workspaces) use ($today, &$errors, &$handled) {
            foreach ($workspaces as $workspace) {
                $subscription = $workspace->liveSubscription();

                if (! $this->isProcessable($subscription)) {
                    continue;
                }

                $renewal = $subscription->renews_at->copy()->startOfDay();

                try {
                    $this->cancelSuperseded($workspace, $subscription);

                    if ($today->gte($renewal->copy()->subDays(self::RENEWAL_WINDOW_DAYS)) && $today->lt($renewal)) {
                        $this->prepareRenewal($workspace, $subscription, $today->equalTo($renewal->copy()->subDay()));
                        $handled++;
                    } elseif ($this->isAnniversary($today, $renewal)) {
                        $this->invoiceExtraUsage($workspace, $subscription);
                        $handled++;
                    }
                } catch (Throwable $e) {
                    $errors++;
                    report($e);
                    $this->error("Workspace {$workspace->id} (subscription {$subscription->lemon_squeezy_id}): {$e->getMessage()}");
                }
            }
        });

        $this->info("Completed: {$handled} yearly subscriptions handled, {$errors} errors");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Retry what CancelSupersededSubscription could not do when the yearly subscription
     * arrived, so a monthly subscription never keeps renewing on top of a paid year.
     */
    private function cancelSuperseded(Workspace $workspace, Subscription $yearly): void
    {
        foreach ($workspace->subscriptionsSupersededBy($yearly) as $superseded) {
            $this->line("Workspace {$workspace->id}: cancel superseded subscription {$superseded->lemon_squeezy_id}");

            if (! $this->option('dry-run')) {
                $superseded->cancel();
            }
        }
    }

    private function isProcessable(?Subscription $subscription): bool
    {
        return $subscription !== null
            && $subscription->renews_at !== null
            && $subscription->ends_at === null
            && in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE], true)
            && BillingPlans::isYearly($subscription);
    }

    /**
     * The renewal's day of the month, or the last day of a month too short to have it.
     * Never the renewal date itself: that day the renewal bills the whole year.
     */
    private function isAnniversary(CarbonInterface $today, CarbonInterface $renewal): bool
    {
        return ! $today->equalTo($renewal)
            && $today->day === min($renewal->day, $today->daysInMonth);
    }

    private function invoiceExtraUsage(Workspace $workspace, Subscription $subscription): void
    {
        $itemId = $this->itemId($subscription);
        $paid = $this->usage->getQuantity($itemId) ?? throw new RuntimeException('Could not read the paid quantity');
        $units = $this->units($workspace);

        if ($units <= $paid) {
            $this->line("Workspace {$workspace->id}: {$units} units within the {$paid} paid for");

            return;
        }

        $this->line("Workspace {$workspace->id}: invoice {$units} units now (paid for {$paid})");

        if ($this->option('dry-run')) {
            return;
        }

        if (! $this->usage->updateQuantity($itemId, $units, invoiceImmediately: true)) {
            throw new RuntimeException('Lemon Squeezy refused the quantity update');
        }
    }

    /**
     * Set the quantity the renewal bills for and, on the last day of the paid year, make a
     * requested switch to monthly.
     *
     * Verified in test mode: changing the variant to monthly starts a monthly cycle on the
     * spot and invoices it straight away, for the quantity the item has at that moment, even
     * with disable_prorations. Hence the order (quantity first) and the timing (only the last
     * day, so the customer pays at most one day twice). A refused swap is reported and left
     * for us to finish by hand; the request stays in place.
     */
    private function prepareRenewal(Workspace $workspace, Subscription $subscription, bool $isLastDay): void
    {
        $units = $this->units($workspace);
        $itemId = $this->itemId($subscription);

        if ($this->usage->getQuantity($itemId) !== $units) {
            $this->line("Workspace {$workspace->id}: renewal set to {$units} units");

            if (! $this->option('dry-run')) {
                $this->setRenewalQuantity($itemId, $units);
            }
        }

        if ($workspace->switch_to_monthly_requested_at === null || ! $isLastDay) {
            return;
        }

        $this->line("Workspace {$workspace->id}: switch to monthly, {$units} units");

        if ($this->option('dry-run')) {
            return;
        }

        // Throws on a refusal, which keeps the request in place.
        $subscription->swap(BillingPlans::switchProductId(), BillingPlans::switchMonthlyVariantId(), [
            'disable_prorations' => true,
        ]);

        $workspace->forceFill(['switch_to_monthly_requested_at' => null])->save();

        // The swap may have replaced the subscription item.
        $this->usage->forgetSubscriptionItemId($subscription->lemon_squeezy_id);
    }

    private function setRenewalQuantity(string $itemId, int $units): void
    {
        if (! $this->usage->updateQuantity($itemId, $units, disableProrations: true)) {
            throw new RuntimeException('Lemon Squeezy refused the renewal quantity');
        }
    }

    private function itemId(Subscription $subscription): string
    {
        return $this->usage->resolveSubscriptionItemId($subscription->lemon_squeezy_id)
            ?? throw new RuntimeException('No subscription item to bill against');
    }

    /**
     * Billed for at least one unit, the same floor as the checkout.
     */
    private function units(Workspace $workspace): int
    {
        return max(1, $workspace->getTotalUsageCount());
    }

    private function workspaces()
    {
        return Workspace::query()
            ->where('is_unlimited', false)
            ->where('is_manually_billed', false)
            ->whereHas('subscriptions', fn ($query) => $query->whereNull('ends_at'))
            ->with(['subscriptions' => fn ($query) => $query->orderByDesc('created_at')]);
    }
}
