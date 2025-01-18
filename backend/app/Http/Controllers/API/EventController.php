<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\EventResource;
use App\Models\Display;
use App\Services\EventService;
use App\Services\OutlookService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(protected OutlookService $outlookService, protected EventService $eventService)
    {
    }

    /**
     * @throws \Exception
     */
    public function getAll(): AnonymousResourceCollection
    {
        $events = [];
        $display = auth()->user()->display;

        // Fetch events if device is connected to a display
        if ($display !== null) {
            $events = cache()->remember(
                key: $display->getEventsCacheKey(),
                ttl: now()->addMinutes(15),
                callback: fn () => $this->fetchEventsRemotely($display)
            );
        }

        return EventResource::collection($events);
    }

    /**
     * @throws \Exception
     */
    private function fetchEventsRemotely(Display $display): array
    {
        $outlookEvents = $this->outlookService->fetchEvents(
            outlookAccount: $display->calendar->outlookAccount,
            emailAddress: $display->calendar->room->email_address,
            startDateTime: $display->getStartTime(),
            endDateTime: $display->getEndTime(),
        );

        return collect($outlookEvents)
            ->map(fn ($e) => $this->eventService->sanitizeEvent($e))
            ->toArray();
    }
}
