<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\Workspace;
use App\Services\FreePlanDisplayLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The free plan's one active display, from the command line.
 *
 * --sync is the scheduled catch-up for missed webhooks: it brings back the displays of every
 * workspace that has Pro again, and applies the free plan only to workspaces whose
 * subscription definitively ended in the last two days. It never touches a workspace that
 * was already free before, so nothing changes for anyone who did not just change plan.
 *
 * --list shows free workspaces that have more than one live display today, and changes
 * nothing. --workspace applies the free plan to one workspace on purpose.
 */
class FreePlanDisplays extends Command
{
    protected $signature = 'app:free-plan-displays
        {--list : Show free workspaces with more than one live display, without changing anything}
        {--workspace= : Apply the free plan to this one workspace}
        {--sync : Catch up on recent plan changes (scheduled)}';

    protected $description = 'Free plan: one active display per workspace';

    public function handle(FreePlanDisplayLimit $freePlan): int
    {
        if (config('settings.is_self_hosted')) {
            $this->info('Not used on a self-hosted instance.');

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            return $this->list($freePlan);
        }

        if ($id = $this->option('workspace')) {
            $workspace = Workspace::find($id);

            if (! $workspace) {
                $this->error("Workspace {$id} not found.");

                return self::FAILURE;
            }

            $count = $freePlan->apply($workspace);
            $this->info("{$count} display(s) in \"{$workspace->name}\" now wait for Pro.");

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            return $this->sync($freePlan);
        }

        $this->error('Pass --list, --workspace=<id> or --sync.');

        return self::FAILURE;
    }

    private function list(FreePlanDisplayLimit $freePlan): int
    {
        $rows = Workspace::query()
            ->whereHas('displays', fn ($query) => $query->whereIn('status', FreePlanDisplayLimit::liveStatuses()), '>', 1)
            ->withCount(['displays as live_displays_count' => fn ($query) => $query->whereIn('status', FreePlanDisplayLimit::liveStatuses())])
            ->orderByDesc('live_displays_count')
            ->get()
            ->filter(fn (Workspace $workspace) => $freePlan->applies($workspace))
            ->map(fn (Workspace $workspace) => [
                $workspace->id,
                $workspace->name,
                $workspace->billingOwner()?->email,
                $workspace->live_displays_count,
            ]);

        $this->table(['Workspace', 'Name', 'Owner', 'Live displays'], $rows->values()->all());
        $this->info($rows->count().' workspace(s). Nothing was changed.');

        return self::SUCCESS;
    }

    private function sync(FreePlanDisplayLimit $freePlan): int
    {
        $released = 0;
        Workspace::query()
            ->whereHas('displays', fn ($query) => $query->where('status', DisplayStatus::REQUIRES_PRO))
            ->each(function (Workspace $workspace) use ($freePlan, &$released) {
                $released += $freePlan->release($workspace);
            });

        // Definitively ended: expired, or past its end date. A failed payment is still being
        // retried and keeps its displays.
        $since = now()->subHours(48);
        $endedWorkspaceIds = DB::table('lemon_squeezy_subscriptions')
            ->where('billable_type', (new Workspace)->getMorphClass())
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('status', 'expired')->where('updated_at', '>=', $since))
                ->orWhere(fn ($q) => $q->whereBetween('ends_at', [$since, now()])))
            ->distinct()
            ->pluck('billable_id');

        $paused = 0;
        Workspace::whereIn('id', $endedWorkspaceIds)->each(function (Workspace $workspace) use ($freePlan, &$paused) {
            $paused += $freePlan->apply($workspace);
        });

        $this->info("{$released} display(s) back, {$paused} display(s) now wait for Pro.");

        return self::SUCCESS;
    }
}
