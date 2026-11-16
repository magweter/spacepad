<?php

namespace App\Console\Commands;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Services\EventService;
use App\Support\PortalEvents;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Keeps the event cache of every display in use filled, so the portal (overview, displays) and
 * the boards read their rooms from the cache instead of asking a calendar while someone waits.
 *
 * Only displays whose cache is empty are fetched: a tablet that polls, or a webhook, keeps the
 * rest warm already, so this adds calendar calls only where nothing else does. "In use" means a
 * tablet was seen in the past week or the display is on a board. A run stops asking after
 * --budget seconds; the next minute's run picks up the rest.
 */
class WarmEventCaches extends Command
{
    protected $signature = 'app:warm-event-caches {--budget=50 : Seconds a run may spend asking calendars}';

    protected $description = 'Fill the empty event caches of displays in use, so pages and boards never wait on a calendar';

    public function handle(EventService $events): int
    {
        if (! config('services.events.cache_enabled')) {
            $this->info('Event caching is off; nothing to warm.');

            return self::SUCCESS;
        }

        $deadline = microtime(true) + (int) $this->option('budget');
        $warmed = 0;
        $failed = 0;

        foreach ($this->displaysInUse()->lazyById(100) as $display) {
            if (microtime(true) > $deadline) {
                $this->warn('Time budget used up; the rest follows next run.');
                break;
            }

            try {
                if ($events->warmCache($display)) {
                    $warmed++;
                    PortalEvents::clearFailed($display);
                }
            } catch (Throwable $e) {
                // The portal shows this room as not loaded and lists it under "Needs attention".
                PortalEvents::markFailed($display);
                $failed++;
                report($e);
            }
        }

        $this->info("Warmed {$warmed} display caches".($failed ? ", {$failed} failed" : '').'.');

        return self::SUCCESS;
    }

    /**
     * @return Builder<Display>
     */
    private function displaysInUse(): Builder
    {
        $boardWorkspaces = Board::where('show_all_displays', true)->select('workspace_id');

        return Display::query()
            ->whereIn('status', [DisplayStatus::ACTIVE, DisplayStatus::READY])
            ->whereNotNull('calendar_id')
            ->where(fn (Builder $query) => $query
                ->whereHas('devices', fn (Builder $q) => $q->where('last_activity_at', '>=', now()->subWeek()))
                ->orWhereHas('boards')
                ->orWhereIn('workspace_id', $boardWorkspaces));
    }
}
