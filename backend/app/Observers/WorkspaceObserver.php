<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Services\FreePlanDisplayLimit;

/**
 * Pro granted by hand (unlimited, or invoiced by us) brings waiting displays back, the same
 * as a subscription does.
 */
class WorkspaceObserver
{
    public function __construct(private FreePlanDisplayLimit $freePlan) {}

    public function updated(Workspace $workspace): void
    {
        if ($workspace->wasChanged(['is_unlimited', 'is_manually_billed'])) {
            $this->freePlan->release($workspace);
        }
    }
}
