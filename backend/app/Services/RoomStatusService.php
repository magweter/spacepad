<?php

namespace App\Services;

use App\Data\RoomStatus;
use Illuminate\Support\Collection;

class RoomStatusService
{
    public function compute(Collection $events): RoomStatus
    {
        $now = now();
        $currentEvent = $events->first(fn ($e) => $e->start <= $now && $e->end > $now);
        $nextEvent = $events->filter(fn ($e) => $e->start > $now)->first();

        $minutesUntilNext = $nextEvent ? $now->diffInMinutes($nextEvent->start, true) : null;
        $isTransitioning = !$currentEvent && $minutesUntilNext !== null && $minutesUntilNext <= 15;

        $status = match (true) {
            (bool) $currentEvent => 'reserved',
            $isTransitioning     => 'transitioning',
            default              => 'available',
        };

        $timezone = $currentEvent?->timezone ?? $nextEvent?->timezone ?? config('app.timezone');

        return new RoomStatus($status, $currentEvent, $nextEvent, $timezone);
    }
}
