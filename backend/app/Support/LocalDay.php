<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The calendar day of the tablet that is asking, expressed as a UTC instant range.
 *
 * A display can hang in any timezone while the server runs in another (usually UTC), and a UTC day
 * boundary can be up to ~14 hours away from the display's local one. Deriving the day server-side
 * is therefore guesswork: it either cuts off part of the local day or — when widened to compensate —
 * leaks the next day's bookings into the payload, which is what made a tablet show tomorrow's first
 * meeting as "Next" late in the evening.
 *
 * So the tablet states its own day: `X-Local-Date` (its calendar date) plus `X-Utc-Offset` (its
 * current offset from UTC in minutes). Clients that send neither — older app builds — fall back to
 * the server's own day, which is exactly how things behaved before.
 */
final readonly class LocalDay
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    /**
     * The day the requesting tablet is on, or null when it did not say.
     *
     * Null is meaningful: callers keep their previous behaviour for it, so an app build from before
     * these headers existed sees exactly what it saw before.
     */
    public static function tryFromRequest(Request $request, ?Carbon $forDate = null): ?self
    {
        $offset = self::offsetFromRequest($request);
        $date = $forDate?->format('Y-m-d') ?? self::dateFromRequest($request);

        if ($date === null || $offset === null) {
            return null;
        }

        return self::fromDateAndOffset($date, $offset);
    }

    /**
     * Build the window from a calendar date and the client's offset from UTC in minutes
     * (e.g. 120 for CEST, -480 for PST).
     */
    public static function fromDateAndOffset(string $date, int $offsetMinutes): self
    {
        // Local midnight expressed in UTC: subtract the offset that is ahead of UTC.
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', 'UTC')
            ->subMinutes($offsetMinutes);

        return new self($start, $start->copy()->addDay()->subSecond());
    }

    /**
     * The server's own day, used when the client does not state its timezone.
     */
    public static function serverDay(Carbon $date): self
    {
        return new self($date->copy()->startOfDay(), $date->copy()->endOfDay());
    }

    /**
     * Whether an event touches this day. Overlap rather than containment, so a meeting that runs
     * across midnight still belongs to both days instead of disappearing from one.
     */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $start->lt($this->end) && $end->gt($this->start);
    }

    /**
     * The range to ask the calendar providers for: a day either side of the window.
     *
     * Providers are queried wide on purpose. It costs nothing, it means a booking near the local
     * midnight can never fall outside the request, and the wide result stays shareable in the cache
     * between tablets in different timezones — the narrowing happens per request.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function fetchRange(): array
    {
        return [
            $this->start->copy()->subDay(),
            $this->end->copy()->addDay(),
        ];
    }

    private static function dateFromRequest(Request $request): ?string
    {
        $date = $request->header('X-Local-Date');

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        // Reject something like 2026-13-45 that matches the shape but is not a date.
        return Carbon::hasFormat($date, 'Y-m-d') ? $date : null;
    }

    private static function offsetFromRequest(Request $request): ?int
    {
        $offset = $request->header('X-Utc-Offset');

        if (! is_string($offset) || preg_match('/^-?\d{1,4}$/', $offset) !== 1) {
            return null;
        }

        $minutes = (int) $offset;

        // Real offsets run from -12:00 to +14:00.
        return $minutes >= -720 && $minutes <= 840 ? $minutes : null;
    }
}
