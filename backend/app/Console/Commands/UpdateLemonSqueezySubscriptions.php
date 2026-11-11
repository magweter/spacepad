<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\BillingPlans;
use App\Services\LemonSqueezyUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Push each workspace's billable usage to Lemon Squeezy.
 *
 * Previously iterated users and summed usage across *every* workspace they were a member
 * of, so a person invited into a colleague's workspace had that colleague's displays and
 * boards added to their own subscription quantity. Harmless while everyone had exactly one
 * workspace; a real over-billing bug the moment invitations exist.
 *
 * Since usage changes push themselves through PushWorkspaceUsageToLemonSqueezy, this is
 * no longer how quantities normally reach Lemon Squeezy. It stays scheduled as the
 * reconciliation pass: it walks every billable workspace, so a queue job that was lost or
 * rejected is put right within the hour.
 */
class UpdateLemonSqueezySubscriptions extends Command
{
    protected $signature = 'app:update-lemonsqueezy-subscriptions {--dry-run : Show what would be pushed without calling the API}';

    protected $description = 'Push billable usage (displays x1 + boards x2) per workspace to Lemon Squeezy';

    public function __construct(private readonly LemonSqueezyUsageService $usage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Skipping subscription update - this is a self-hosted instance');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: no API calls will be made.');
        }

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        $this->billableWorkspaces()->chunkById(200, function ($workspaces) use (&$successCount, &$errorCount, &$skippedCount, $dryRun) {
            foreach ($workspaces as $workspace) {
                $units = $workspace->getTotalUsageCount();

                try {
                    if ($workspace->is_manually_billed) {
                        // Invoiced through our own accounting, so there is nothing at Lemon
                        // Squeezy to update.
                        $this->line("Skipping manually-billed workspace {$workspace->id} ({$units} units)");
                        $skippedCount++;

                        continue;
                    }

                    if ($workspace->is_unlimited) {
                        $this->line("Skipping unlimited workspace {$workspace->id} ({$units} units)");
                        $skippedCount++;

                        continue;
                    }

                    $subscription = $workspace->liveSubscription();

                    if (! $subscription) {
                        $skippedCount++;

                        continue;
                    }

                    // Billed a year ahead and trued up by app:process-yearly-subscriptions.
                    if (BillingPlans::isYearly($subscription)) {
                        $this->line("Skipping yearly subscription of workspace {$workspace->id} ({$units} units)");
                        $skippedCount++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            'workspace=%s subscription=%s units=%d',
                            $workspace->id,
                            $subscription->lemon_squeezy_id,
                            $units,
                        ));
                        $successCount++;

                        continue;
                    }

                    if (! $this->usage->hasApiKey()) {
                        $this->warn('No Lemon Squeezy API key configured; nothing pushed.');
                        $skippedCount++;

                        continue;
                    }

                    // Failures are logged inside the service and counted as errors here.
                    // They used to go to Log::debug and still count as a success, so a
                    // broken push looked like a clean run. The console line matters as much
                    // as the log: the scheduler sends a command's output to /dev/null, so
                    // for a while the only sign of trouble was the exit code, with nothing
                    // to say which workspace caused it.
                    if (! $this->usage->pushUnits($subscription->lemon_squeezy_id, $units, ['workspace_id' => $workspace->id])) {
                        // A refusal is often Lemon Squeezy's way of saying the subscription
                        // is not what we think it is: a missed webhook left one expired
                        // subscription looking live for months. Pull the truth before
                        // deciding whether this is an error at all.
                        $remote = $this->usage->refreshSubscription($subscription);

                        if ($remote !== null && ! $subscription->valid()) {
                            $this->line(sprintf(
                                'Skipping workspace %s: subscription %s is %s at Lemon Squeezy, local row synced',
                                $workspace->id,
                                $subscription->lemon_squeezy_id,
                                $subscription->status,
                            ));
                            $skippedCount++;

                            continue;
                        }

                        // Nothing we can do from here: Lemon Squeezy only lets the customer
                        // change a PayPal subscription, through the portal. Say so with the
                        // numbers, since the quantity at Lemon Squeezy will drift from the
                        // real usage until someone acts on it.
                        if (($remote['payment_processor'] ?? null) === 'paypal') {
                            $billed = $remote['first_subscription_item']['quantity'] ?? null;
                            $message = sprintf(
                                'Workspace %s pays through PayPal, which Lemon Squeezy cannot update via the API: billed for %s, uses %d units',
                                $workspace->id,
                                $billed ?? '?',
                                $units,
                            );
                            $this->warn($message);
                            if ($billed !== $units) {
                                Log::warning($message, ['workspace_id' => $workspace->id, 'subscription_id' => $subscription->lemon_squeezy_id]);
                            }
                            $skippedCount++;

                            continue;
                        }

                        $errorCount++;
                        $this->error(sprintf(
                            'Lemon Squeezy refused workspace %s (subscription %s, %d units)',
                            $workspace->id,
                            $subscription->lemon_squeezy_id,
                            $units,
                        ));

                        continue;
                    }

                    $successCount++;
                    $this->info("Updated workspace {$workspace->id} with {$units} units (displays + boards*2)");
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to update workspace {$workspace->id}: {$e->getMessage()}");
                    Log::error('Workspace usage push failed', [
                        'workspace_id' => $workspace->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Completed: {$successCount} pushed, {$skippedCount} skipped, {$errorCount} errors");

        return $errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Workspaces that are either unlimited or carry a live subscription.
     */
    private function billableWorkspaces()
    {
        return Workspace::query()
            ->where(function ($query) {
                $query->where('is_unlimited', true)
                    ->orWhere('is_manually_billed', true)
                    ->orWhereHas('subscriptions', function ($subQuery) {
                        $subQuery->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
            })
            ->with(['subscriptions' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })->orderByDesc('created_at');
            }]);
    }
}
