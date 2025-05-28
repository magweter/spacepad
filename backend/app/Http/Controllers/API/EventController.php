<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\EventResource;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Services\EventService;
use App\Services\OutlookService;
use App\Services\GoogleService;
use App\Services\CalDAVService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService,
        protected EventService $eventService
    ) {
    }

    /**
     * @throws \Exception
     */
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        /** @var Device $device */
        $device = auth()->user();
        $display = $device->display()->with(['calendar', 'user'])->first();

        // Check if the device is connected to a display
        if (! $display) {
            return response()->json(['message' => 'Device is not connected to a display'], 400);
        }

        // Check if the display is active
        if ($display->isDeactivated()) {
            return response()->json(['message' => 'Display is deactivated'], 400);
        }

        // Check if the user has access
        if (! $display->user->hasAccess()) {
            return response()->json(['message' => 'Your trial has expired. Please subscribe to continue using Spacepad.'], 403);
        }

        // Cache events if enabled and not a CalDAV integration, since that doesn't support webhooks
        if (config('services.events.cache_enabled') && ! $display->calendar->caldav_account_id) {
            $events = cache()->remember(
                key: $display->getEventsCacheKey(),
                ttl: now()->addMinutes(15),
                callback: fn () => $this->fetchEventsRemotely($display)
            );
        } else {
            $events = $this->fetchEventsRemotely($display);
        }

        // Update last sync timestamp
        $display->updateLastSyncAt();

        return EventResource::collection($events);
    }

    /**
     * @throws \Exception
     */
    private function fetchEventsRemotely(Display $display): array
    {
        $calendar = $display->calendar()
            ->with(['googleAccount', 'outlookAccount', 'caldavAccount', 'room'])
            ->first();

        // Handle Google integration
        if ($calendar->google_account_id) {
            return $this->fetchGoogleEvents($calendar, $display);
        }

        // Handle Outlook integration
        if ($calendar->outlook_account_id) {
            return $this->fetchOutlookEvents($calendar, $display);
        }

        // Handle CalDAV integration
        if ($calendar->caldav_account_id) {
            return $this->fetchCalDAVEvents($calendar, $display);
        }

        return [];
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return array
     * @throws \Exception
     */
    private function fetchOutlookEvents(Calendar $calendar, Display $display): array
    {
        $events = [];

        // Fetch events by user (room)
        if ($calendar->room) {
            $events = $this->outlookService->fetchEventsByUser(
                outlookAccount: $calendar->outlookAccount,
                emailAddress: $calendar->calendar_id,
                startDateTime: $display->getStartTime(),
                endDateTime: $display->getEndTime(),
            );
        }

        // Fetch events by calendar
        if (! $calendar->room) {
            $events = $this->outlookService->fetchEventsByCalendar(
                outlookAccount: $calendar->outlookAccount,
                calendarId: $calendar->calendar_id,
                startDateTime: $display->getStartTime(),
                endDateTime: $display->getEndTime(),
            );
        }

        return collect($events)
            ->map(fn($e) => $this->eventService->sanitizeOutlookEvent($e))
            ->toArray();
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return array
     * @throws \Exception
     */
    private function fetchGoogleEvents(Calendar $calendar, Display $display): array
    {
        $events = $this->googleService->fetchEvents(
            googleAccount: $calendar->googleAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $display->getStartTime(),
            endDateTime: $display->getEndTime(),
        );

        return collect($events)
            ->map(fn($e) => $this->eventService->sanitizeGoogleEvent($e))
            ->toArray();
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return array
     * @throws \Exception
     */
    private function fetchCalDAVEvents(Calendar $calendar, Display $display): array
    {
        $events = $this->caldavService->fetchEvents(
            caldavAccount: $calendar->caldavAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $display->getStartTime(),
            endDateTime: $display->getEndTime(),
        );

        return collect($events)
            ->map(fn($e) => $this->eventService->sanitizeCalDAVEvent($e))
            ->toArray();
    }
}
