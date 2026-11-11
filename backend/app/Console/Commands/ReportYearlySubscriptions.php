<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\BillingPlans;
use App\Services\LemonSqueezyUsageService;
use Illuminate\Console\Command;

/**
 * List yearly subscriptions with their usage next to the quantity Lemon Squeezy bills.
 *
 * Until app:process-yearly-subscriptions existed, the usage push resized yearly
 * subscriptions like monthly ones, which queues prorated charges and credits for the
 * renewal. This only reads, so each mismatch can be judged by hand.
 */
class ReportYearlySubscriptions extends Command
{
    protected $signature = 'app:report-yearly-subscriptions';

    protected $description = 'List yearly subscriptions whose Lemon Squeezy quantity differs from usage (read-only)';

    public function handle(LemonSqueezyUsageService $usage): int
    {
        $rows = [];

        Workspace::query()
            ->whereHas('subscriptions', fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('subscriptions')
            ->chunkById(200, function ($workspaces) use ($usage, &$rows) {
                foreach ($workspaces as $workspace) {
                    $subscription = $workspace->liveSubscription();

                    if (! BillingPlans::isYearly($subscription)) {
                        continue;
                    }

                    $itemId = $usage->resolveSubscriptionItemId($subscription->lemon_squeezy_id);
                    $quantity = $itemId ? $usage->getQuantity($itemId) : null;
                    $units = $workspace->getTotalUsageCount();

                    $rows[] = [
                        $workspace->id,
                        $workspace->name,
                        $subscription->lemon_squeezy_id,
                        $units,
                        $quantity ?? '?',
                        $subscription->renews_at?->toDateString() ?? '-',
                        $quantity === $units ? '' : 'mismatch',
                    ];
                }
            });

        $this->table(['Workspace', 'Name', 'Subscription', 'Usage', 'LS quantity', 'Renews', ''], $rows);
        $this->info(count($rows).' yearly subscriptions');

        return self::SUCCESS;
    }
}
