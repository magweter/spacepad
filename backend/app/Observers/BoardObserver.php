<?php

namespace App\Observers;

use App\Models\Board;
use App\Models\Workspace;
use App\Services\WorkspaceUsageService;

/**
 * Keeps workspaces.boards_count in step with the boards themselves.
 *
 * A board is worth two billable units, but that multiplier lives in
 * Workspace::calculateUsage — here a board is simply one board.
 *
 * Note the gap this cannot cover: boards.workspace_id cascades on delete, so removing a
 * workspace takes its boards without firing anything. That is harmless, because the
 * counter row goes with it.
 */
class BoardObserver
{
    public function __construct(private WorkspaceUsageService $usage) {}

    public function created(Board $board): void
    {
        $this->adjust($board->workspace_id, 1);
    }

    public function deleted(Board $board): void
    {
        $this->adjust($board->workspace_id, -1);
    }

    /**
     * A board handed to another workspace moves its units along with it.
     */
    public function updated(Board $board): void
    {
        if (! $board->wasChanged('workspace_id')) {
            return;
        }

        $this->adjust($board->getOriginal('workspace_id'), -1);
        $this->adjust($board->workspace_id, 1);
    }

    private function adjust(?string $workspaceId, int $delta): void
    {
        if (! $workspaceId) {
            return;
        }

        $workspace = Workspace::find($workspaceId);

        if ($workspace) {
            $this->usage->apply($workspace, 0, $delta);
        }
    }
}
