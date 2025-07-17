<?php

namespace App\Console\Commands;

use App\Models\Display;
use App\Models\Event;
use Illuminate\Console\Command;

class CleanupExpiredEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-expired-events {--display= : Specific display ID to cleanup} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup events that have ended before the display timeframe (events from previous days)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $displayId = $this->option('display');
        $dryRun = $this->option('dry-run');

        if ($displayId) {
            $displays = Display::where('id', $displayId)->get();
            if ($displays->isEmpty()) {
                $this->error("Display with ID {$displayId} not found.");
                return;
            }
        } else {
            $displays = Display::all();
        }

        $totalDeleted = 0;

        foreach ($displays as $display) {
            $deletedCount = $this->cleanupEventsForDisplay($display, $dryRun);
            $totalDeleted += $deletedCount;
            
            if ($deletedCount > 0) {
                $action = $dryRun ? 'would delete' : 'deleted';
                $this->info("Display '{$display->name}' (ID: {$display->id}): {$action} {$deletedCount} expired events");
            }
        }

        $action = $dryRun ? 'would be deleted' : 'deleted';
        $this->info("Total: {$totalDeleted} events {$action}");
    }

    /**
     * Cleanup expired events for a specific display
     */
    private function cleanupEventsForDisplay(Display $display, bool $dryRun = false): int
    {
        $startTime = $display->getStartTime();

        $query = Event::where('display_id', $display->id)
            ->where('end', '<', $startTime);

        if ($dryRun) {
            return $query->count();
        }

        $deletedCount = $query->count();
        $query->delete();

        if ($deletedCount > 0) {
            logger()->info("Cleaned up {$deletedCount} expired events for display {$display->id} that ended before {$startTime->toDateTimeString()}");
        }

        return $deletedCount;
    }
}