<?php

namespace App\Services;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The free plan has one active display per workspace.
 *
 * Creating a second display already needs Pro, so a free workspace only ends up with more
 * than one when it gets them some other way: a subscription that ran out, or two workspaces
 * that were combined. The extra displays then wait as REQUIRES_PRO until the workspace has
 * Pro again, and come back on their own when it does.
 *
 * Callers decide when this runs. Nothing here looks at history: apply() on a workspace that
 * has been free for years would pause its displays too, which is why only transitions call it.
 */
class FreePlanDisplayLimit
{
    /**
     * Statuses of a display that is in use, as opposed to switched off or waiting for Pro.
     *
     * @return array<int, DisplayStatus>
     */
    public static function liveStatuses(): array
    {
        return [DisplayStatus::READY, DisplayStatus::ACTIVE, DisplayStatus::ERROR];
    }

    /**
     * Whether the one-display rule holds for this workspace right now.
     */
    public function applies(Workspace $workspace): bool
    {
        return ! config('settings.is_self_hosted') && ! $workspace->hasPro();
    }

    /**
     * Keep the oldest live display, let the others wait for Pro.
     *
     * @return int how many displays now wait for Pro
     */
    public function apply(Workspace $workspace): int
    {
        if (! $this->applies($workspace)) {
            return 0;
        }

        $extra = $workspace->displays()
            ->whereIn('status', self::liveStatuses())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->skip(1);

        foreach ($extra as $display) {
            $display->update(['status' => DisplayStatus::REQUIRES_PRO]);
        }

        if ($extra->isNotEmpty()) {
            logger()->info('Free plan: displays now wait for Pro', [
                'workspace_id' => $workspace->id,
                'display_ids' => $extra->pluck('id')->values()->all(),
            ]);
        }

        return $extra->count();
    }

    /**
     * Bring back every display that waited for Pro, once the workspace has it.
     *
     * @return int how many displays came back
     */
    public function release(Workspace $workspace): int
    {
        if ($this->applies($workspace)) {
            return 0;
        }

        $waiting = $workspace->displays()
            ->where('status', DisplayStatus::REQUIRES_PRO)
            ->withCount('devices')
            ->get();

        foreach ($waiting as $display) {
            $display->update(['status' => $this->resumedStatus($display)]);
        }

        return $waiting->count();
    }

    /**
     * Swap which display is the free one: the chosen display comes back, the live one waits.
     */
    public function makeActive(Display $display): void
    {
        $workspace = $display->workspace;

        if (! $workspace || ! $display->requiresPro()) {
            return;
        }

        if (! $this->applies($workspace)) {
            $this->release($workspace);

            return;
        }

        DB::transaction(function () use ($workspace, $display) {
            $workspace->displays()
                ->whereIn('status', self::liveStatuses())
                ->update(['status' => DisplayStatus::REQUIRES_PRO]);

            $display->loadCount('devices');
            $display->update(['status' => $this->resumedStatus($display)]);
        });
    }

    /**
     * Whether a free workspace already has its one live display, other than this one.
     */
    public function hasOtherLiveDisplay(Display $display): bool
    {
        return $display->workspace
            && $this->applies($display->workspace)
            && $display->workspace->displays()
                ->whereKeyNot($display->id)
                ->whereIn('status', self::liveStatuses())
                ->exists();
    }

    /**
     * A display that has a tablet on it is active, one without is still waiting to be set up.
     */
    private function resumedStatus(Display $display): DisplayStatus
    {
        $devices = $display->devices_count ?? $display->devices()->count();

        return $devices > 0 ? DisplayStatus::ACTIVE : DisplayStatus::READY;
    }
}
