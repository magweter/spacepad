<?php

namespace App\Services;

use App\Events\WorkspaceUsageChanged;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of workspaces.displays_count / boards_count.
 *
 * Those two columns are what the whole product bills on, so they are maintained here and
 * nowhere else. Everything that creates or deletes a display or a board reaches this
 * service through DisplayObserver / BoardObserver; the bulk paths that bypass model
 * events (WorkspaceTransferService, WorkspaceService::purge) call recount() by hand.
 */
class WorkspaceUsageService
{
    /**
     * Move the counters by a delta, and announce it if the billable total changed.
     *
     * The increment happens inside the database rather than by reading, adding and
     * writing back, so two displays created at the same instant cannot lose a tick. The
     * CASE floor is portable across SQLite, MySQL and Postgres, and stops a
     * double-delete from underflowing an unsigned column mid-request — reconciliation is
     * for finding out that it happened, not for keeping the request alive.
     */
    public function apply(Workspace $workspace, int $displayDelta, int $boardDelta): void
    {
        if ($displayDelta === 0 && $boardDelta === 0) {
            return;
        }

        DB::update(
            'update workspaces set '
            .'displays_count = case when displays_count + ? < 0 then 0 else displays_count + ? end, '
            .'boards_count = case when boards_count + ? < 0 then 0 else boards_count + ? end '
            .'where id = ?',
            [$displayDelta, $displayDelta, $boardDelta, $boardDelta, $workspace->id]
        );

        $fresh = DB::table('workspaces')
            ->where('id', $workspace->id)
            ->first(['displays_count', 'boards_count']);

        if (! $fresh) {
            return;
        }

        $this->publish(
            $workspace,
            previousDisplays: (int) $fresh->displays_count - $displayDelta,
            previousBoards: (int) $fresh->boards_count - $boardDelta,
            newDisplays: (int) $fresh->displays_count,
            newBoards: (int) $fresh->boards_count,
        );
    }

    /**
     * Recount from the rows themselves and write the answer.
     *
     * The repair path: used by app:reconcile-workspace-usage and after any bulk operation
     * that moves or deletes rows without firing model events. Announces the result like
     * any other change, so a workspace that gained ten displays through a merge updates
     * its subscription exactly as it would have one display at a time.
     */
    public function recount(Workspace $workspace): void
    {
        $displays = $workspace->displays()->count();
        $boards = $workspace->boards()->count();

        $previousDisplays = (int) $workspace->displays_count;
        $previousBoards = (int) $workspace->boards_count;

        if ($displays === $previousDisplays && $boards === $previousBoards) {
            return;
        }

        DB::table('workspaces')->where('id', $workspace->id)->update([
            'displays_count' => $displays,
            'boards_count' => $boards,
        ]);

        $this->publish($workspace, $previousDisplays, $previousBoards, $displays, $boards);
    }

    /**
     * Zero both counters without announcing anything.
     *
     * For a workspace being retired: its rows are on their way out along with the
     * workspace itself, and there is no subscription left to resize or change to audit.
     */
    public function reset(Workspace $workspace): void
    {
        DB::table('workspaces')->where('id', $workspace->id)->update([
            'displays_count' => 0,
            'boards_count' => 0,
        ]);

        $workspace->forceFill(['displays_count' => 0, 'boards_count' => 0])->syncOriginalAttributes([
            'displays_count', 'boards_count',
        ]);
    }

    /**
     * Keep the in-memory model honest, then announce a genuine change in billable units.
     *
     * A swap that leaves the total untouched — two displays out, one board in — writes
     * the columns but raises nothing: there is no new quantity to send and nothing to
     * record on the invoice trail.
     */
    private function publish(
        Workspace $workspace,
        int $previousDisplays,
        int $previousBoards,
        int $newDisplays,
        int $newBoards,
    ): void {
        $workspace->forceFill([
            'displays_count' => $newDisplays,
            'boards_count' => $newBoards,
        ])->syncOriginalAttributes(['displays_count', 'boards_count']);

        if (Workspace::calculateUsage($newDisplays, $newBoards) === Workspace::calculateUsage($previousDisplays, $previousBoards)) {
            return;
        }

        WorkspaceUsageChanged::dispatch(
            $workspace,
            $previousDisplays,
            $previousBoards,
            $newDisplays,
            $newBoards,
        );
    }
}
