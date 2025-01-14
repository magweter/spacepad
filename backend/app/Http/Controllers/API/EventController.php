<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\EventResource;
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
        $display = auth()->user()->display;

        // Fetch events if device is connected to a display
        $events = $display !== null ?
            cache()->remember("display:$display->id:events", now()->addMinutes(15), function () use ($display) {
                $outlookEvents = $this->outlookService->fetchEvents(
                    outlookAccount: $display->calendar->outlookAccount,
                    emailAddress: $display->calendar->room->email_address,
                    startDateTime: now()->subDay(),
                    endDateTime: now()->addDay()
                );
                return collect($outlookEvents)->map(fn ($e) => $this->eventService->sanitizeEvent($e))->toArray();
            }) : [];

        return EventResource::collection($events);
    }
}
