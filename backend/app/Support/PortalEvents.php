<?php

namespace App\Support;

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Services\EventService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A display's events for the portal pages (Overview, Displays), as the tablets see them.
 *
 * Normally they come from the cache the tablets fill, so a portal page never calls a calendar
 * provider. With caching off (local development) there is no such cache, and the events are
 * fetched on the spot. A page lists every display and reloads every minute, so that fetch is
 * guarded: a short timeout on the Microsoft calls, a time budget per page beyond which the
 * remaining displays show "no data yet", its own short cache, and a pause after a failure.
 * A provider that hangs then costs one slow page, not a server that stops answering.
 */
class PortalEvents
{
    /** Seconds one page may spend fetching events in total. */
    private const BUDGET_SECONDS = 6;

    /** Seconds a single Microsoft call may take. */
    private const CALL_TIMEOUT_SECONDS = 4;

    private static ?float $deadline = null;

    public static function for(Display $display): ?Collection
    {
        if (config('services.events.cache_enabled')) {
            return Cache::get($display->getEventsCacheKey()) ?? Cache::get($display->getEventsCacheKey().':fallback');
        }

        if ($display->calendar_id === null || $display->status === DisplayStatus::REQUIRES_PRO) {
            return null;
        }

        $key = "portal:events:{$display->id}";
        if (Cache::has("{$key}:failed")) {
            return null;
        }
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        self::$deadline ??= microtime(true) + self::BUDGET_SECONDS;
        if (microtime(true) > self::$deadline) {
            return null;
        }

        Http::globalOptions(['timeout' => self::CALL_TIMEOUT_SECONDS, 'connect_timeout' => 2]);

        try {
            $events = app(EventService::class)->getEventsForDisplay($display->id);
            Cache::put($key, $events, now()->addMinute());

            return $events;
        } catch (Throwable $e) {
            report($e);
            Cache::put("{$key}:failed", true, now()->addMinutes(5));

            return null;
        } finally {
            Http::globalOptions([]);
        }
    }

    /**
     * Drop the portal's own copy after a change made from the portal, so the next page shows it.
     */
    public static function forget(Display $display): void
    {
        Cache::forget("portal:events:{$display->id}");
        Cache::forget("portal:events:{$display->id}:failed");
    }

    /**
     * Start a new time budget; the next page request gets its own.
     */
    public static function reset(): void
    {
        self::$deadline = null;
    }
}
