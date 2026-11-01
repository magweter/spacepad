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
 *  - The day before renewal, the quantity is set to current usage without proration, so the
 *    renewal bills the new year for what is used then. A requested switch to monthly is
 *    made at the same moment, so the renewal bills a month instead of a year.
 */
class ProcessYearlySubscriptions extends Command
{
    protected $signature = 'app:process-yearly-subscriptions {--dry-run : Show what would happen without changing billing}';

    protected $description = 'Invoice extra usage on yearly subscriptions and prepare their renewal';

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
                    if ($today->equalTo($renewal->copy()->subDay())) {
                        $this->prepareRenewal($workspace, $subscription);
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

    private function prepareRenewal(Workspace $workspace, Subscription $subscription): void
    {
        $units = $this->units($workspace);

        if ($workspace->switch_to_monthly_requested_at !== null) {
            $this->line("Workspace {$workspace->id}: switch to monthly before renewal, {$units} units");

            if ($this->option('dry-run')) {
                return;
            }

            // Throws on a refusal, which keeps the request in place for a retry.
            $subscription->swap(BillingPlans::switchProductId(), BillingPlans::switchMonthlyVariantId(), [
                'disable_prorations' => true,
            ]);

            $workspace->forceFill(['switch_to_monthly_requested_at' => null])->save();

            // The swap may have replaced the subscription item.
            $this->usage->forgetSubscriptionItemId($subscription->lemon_squeezy_id);
            $this->setRenewalQuantity($this->itemId($subscription), $units);

            return;
        }

        $itemId = $this->itemId($subscription);

        if ($this->usage->getQuantity($itemId) === $units) {
            return;
        }

        $this->line("Workspace {$workspace->id}: renewal set to {$units} units");

        if (! $this->option('dry-run')) {
            $this->setRenewalQuantity($itemId, $units);
        }
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
