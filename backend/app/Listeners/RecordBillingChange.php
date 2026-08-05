<?php

namespace App\Listeners;

use App\Events\WorkspaceUsageChanged;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Write the invoice trail entry for a change in billable usage.
 *
 * This used to be inferred by diffing two analytics snapshots, which meant a change was
 * only noticed if it survived until the next run and was attributed to whoever happened
 * to hold the billing at that moment. Hanging it off the event instead makes it exact and
 * immediate: one row per real change, with the counts that actually moved.
 *
 * Written synchronously. It is a single insert, and an audit trail that can be dropped
 * by a lost queue job is not an audit trail.
 */
class RecordBillingChange
{
    public function handle(WorkspaceUsageChanged $event): void
    {
        if (config('settings.is_self_hosted')) {
            return;
        }

        $workspace = $event->workspace;
        $contact = $workspace->billingOwner();

        try {
            DB::table('billing_changes')->insert([
                // Who to contact about it, denormalised so the row survives them leaving.
                'user_id' => $contact?->id,
                'email' => $contact?->email,
                'name' => $contact?->name,
                // What actually changed.
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'previous_displays_count' => $event->previousDisplays,
                'new_displays_count' => $event->newDisplays,
                'previous_boards_count' => $event->previousBoards,
                'new_boards_count' => $event->newBoards,
                'previous_license_count' => $event->previousUnits(),
                'new_license_count' => $event->newUnits(),
                'license_delta' => $event->unitDelta(),
                'change_type' => $event->unitDelta() > 0 ? 'increase' : 'decrease',
                'subscription_status' => $workspace->billingStatus(),
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ] + $this->mrr($workspace, $event));
        } catch (\Exception $e) {
            // No analytics tables (a self-hosted install that slipped past the check above,
            // or a half-migrated environment). Losing the trail must not cost someone their
            // display.
            logger()->warning('Could not record billing change', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * What the change is worth per month, where that is knowable here.
     *
     * A manually billed workspace is priced locally, so both sides are exact. A Lemon
     * Squeezy one is not: the real figure depends on the unit price and interval on the
     * subscription, which only app:refresh-analytics --mrr fetches. Those rows are left
     * null rather than guessed, and that run fills them in.
     *
     * @return array<string, float|null>
     */
    private function mrr(Workspace $workspace, WorkspaceUsageChanged $event): array
    {
        if ($workspace->is_manually_billed) {
            $previous = $workspace->calculateManualMrr($event->previousDisplays, $event->previousBoards);
            $new = $workspace->calculateManualMrr($event->newDisplays, $event->newBoards);

            return [
                'previous_mrr' => $previous,
                'new_mrr' => $new,
                'mrr_delta' => $new - $previous,
            ];
        }

        $previous = DB::table('analytics_workspaces')
            ->where('workspace_id', $workspace->id)
            ->value('mrr_current');

        return [
            'previous_mrr' => $previous === null ? null : (float) $previous,
            'new_mrr' => null,
            'mrr_delta' => null,
        ];
    }
}
