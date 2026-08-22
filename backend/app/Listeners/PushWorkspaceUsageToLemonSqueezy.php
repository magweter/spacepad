<?php

namespace App\Listeners;

use App\Events\WorkspaceUsageChanged;
use App\Services\LemonSqueezyUsageService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Resize the subscription the moment the usage behind it changes.
 *
 * Queued on purpose: creating a display must not wait on an external API, and must not
 * fail because Lemon Squeezy is having a bad afternoon.
 * app:update-lemonsqueezy-subscriptions still runs hourly over every billable workspace,
 * so a job that is lost or rejected is corrected within the hour rather than never.
 */
class PushWorkspaceUsageToLemonSqueezy implements ShouldQueue
{
    public function __construct(private LemonSqueezyUsageService $usage) {}

    public function handle(WorkspaceUsageChanged $event): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $workspace = $event->workspace;

        // Invoiced through our own accounting, or not charged at all: there is nothing at
        // Lemon Squeezy to resize either way.
        if ($workspace->is_manually_billed || $workspace->is_unlimited) {
            return;
        }

        $subscription = $workspace->liveSubscription();

        if (! $subscription) {
            return;
        }

        // The workspace's usage as it stands right now, not the figure the event was
        // raised with. Two changes in quick succession queue two jobs with no guaranteed
        // order, and the last write must not be an older number.
        $this->usage->pushUnits(
            $subscription->lemon_squeezy_id,
            $workspace->getTotalUsageCount(),
            ['workspace_id' => $workspace->id],
        );
    }
}
