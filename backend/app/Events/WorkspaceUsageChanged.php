<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A workspace now bills for a different number of units than it did a moment ago.
 *
 * The single trigger for everything downstream of usage: the Lemon Squeezy quantity and
 * the billing_changes audit trail both hang off this one event, so they cannot drift
 * apart the way two independently scheduled jobs could.
 *
 * Only dispatched when the unit total actually moved. Adding a display and removing a
 * board in the same breath is two events, but replacing a display with another is none.
 */
class WorkspaceUsageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public int $previousDisplays,
        public int $previousBoards,
        public int $newDisplays,
        public int $newBoards,
    ) {}

    public function previousUnits(): int
    {
        return Workspace::calculateUsage($this->previousDisplays, $this->previousBoards);
    }

    public function newUnits(): int
    {
        return Workspace::calculateUsage($this->newDisplays, $this->newBoards);
    }

    public function unitDelta(): int
    {
        return $this->newUnits() - $this->previousUnits();
    }
}
