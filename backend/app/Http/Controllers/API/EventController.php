<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\EventResource;
use App\Models\Display;
use App\Services\EventService;
use App\Services\OutlookService;
use App\Services\GoogleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected EventService $eventService
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getAll(): AnonymousResourceCollection|JsonResponse
    {
        $events = [];
        $display = auth()->user()->display;

        if ($display?->isDeactivated()) {
            return response()->json(['message' => 'Display is deactivated'], 400);
        }

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
        $calendar = $display->calendar()
            ->with(['googleAccount', 'outlookAccount'])
            ->first();

        // Fetch Google events
        if ($calendar->google_account_id) {
            $events = $this->googleService->fetchEvents(
                googleAccount: $calendar->googleAccount,
                calendarId: $calendar->calendar_id,
                startDateTime: $display->getStartTime(),
                endDateTime: $display->getEndTime(),
            );

            return collect($events)
                ->map(fn ($e) => $this->eventService->sanitizeGoogleEvent($e))
                ->toArray();
        }

        // Fetch Outlook events
        if ($calendar->outlook_account_id) {
            $events = $this->outlookService->fetchEvents(
                outlookAccount: $calendar->outlookAccount,
                emailAddress: $calendar->room->email_address,
                startDateTime: $display->getStartTime(),
                endDateTime: $display->getEndTime(),
            );

            return collect($events)
                ->map(fn ($e) => $this->eventService->sanitizeOutlookEvent($e))
                ->toArray();
        }

        return [];
    }
}
