<?php

namespace App\Observers;

use App\Models\Display;
use App\Models\Workspace;
use App\Services\WorkspaceUsageService;

/**
 * Keeps workspaces.displays_count in step with the displays themselves.
 *
 * A display is worth one billable unit, so every create, delete and move has to land on
 * the counter. Displays created before workspaces existed can still have a null
 * workspace_id — those belong to nobody's invoice and are skipped.
 */
class DisplayObserver
{
    public function __construct(private WorkspaceUsageService $usage) {}

    public function created(Display $display): void
    {
        $this->adjust($display->workspace_id, 1);
    }

    public function deleted(Display $display): void
    {
        $this->adjust($display->workspace_id, -1);
    }

    /**
     * A display handed to another workspace moves its unit along with it.
     */
    public function updated(Display $display): void
    {
        if (! $display->wasChanged('workspace_id')) {
            return;
        }

        $this->adjust($display->getOriginal('workspace_id'), -1);
        $this->adjust($display->workspace_id, 1);
    }

    private function adjust(?string $workspaceId, int $delta): void
    {
        if (! $workspaceId) {
            return;
        }

        $workspace = Workspace::find($workspaceId);

        if ($workspace) {
            $this->usage->apply($workspace, $delta, 0);
        }
    }
}
