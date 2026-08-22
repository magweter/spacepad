<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WorkspaceUsageService;
use Illuminate\Console\Command;

/**
 * Prove that workspaces.displays_count / boards_count still match reality.
 *
 * The counters are maintained by observers, which covers every path the application
 * takes. This is what makes "they are always right" a claim anyone can check rather than
 * a promise: a raw SQL fix in production, a restored backup, or a future bulk operation
 * that forgets to recount will show up here.
 */
class ReconcileWorkspaceUsage extends Command
{
    protected $signature = 'app:reconcile-workspace-usage {--fix : Write the recounted values instead of only reporting them}';

    protected $description = 'Compare the workspace usage counters against the actual displays and boards';

    public function __construct(private WorkspaceUsageService $usage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $drifted = 0;
        $checked = 0;

        // Aliased deliberately: withCount's default alias for these two relations is
        // exactly the name of the columns being checked, which would compare each stored
        // value against itself and report all-clear forever.
        Workspace::query()
            ->withCount(['displays as actual_displays', 'boards as actual_boards'])
            ->chunkById(200, function ($workspaces) use (&$drifted, &$checked, $fix) {
                foreach ($workspaces as $workspace) {
                    $checked++;

                    $storedDisplays = (int) $workspace->displays_count;
                    $storedBoards = (int) $workspace->boards_count;
                    $actualDisplays = (int) $workspace->actual_displays;
                    $actualBoards = (int) $workspace->actual_boards;

                    if ($storedDisplays === $actualDisplays && $storedBoards === $actualBoards) {
                        continue;
                    }

                    $drifted++;

                    $this->warn(sprintf(
                        '%s (%s): displays %d -> %d, boards %d -> %d',
                        $workspace->name,
                        $workspace->id,
                        $storedDisplays,
                        $actualDisplays,
                        $storedBoards,
                        $actualBoards,
                    ));

                    if ($fix) {
                        $this->usage->recount($workspace);
                    }
                }
            });

        $this->line("Checked {$checked} workspaces, {$drifted} drifted.");

        if ($drifted > 0 && ! $fix) {
            $this->line('Run again with --fix to correct them.');
        }

        return self::SUCCESS;
    }
}
