<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\EventSource;
use App\Enums\PermissionType;
use App\Models\Display;
use App\Models\Event;
use App\Models\Calendar;
use Exception;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EventService
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService,
    ) {
    }

    /**
     * Fetch events for a display, including remote sync if needed.
     * @throws Exception
     */
    public function getEventsForDisplay($display): Collection
    {
        $display = Display::query()->withCount('eventSubscriptions')->findOrFail($display);

        // Update last sync timestamp
        $display->updateLastSyncAt();

        // Release rooms that have not been checked in
        $this->processExpiredCheckIns($display);

        // Cache events if caching is enabled and the display has an event subscription
        $cachingEnabled = config('services.events.cache_enabled') && $display->event_subscriptions_count > 0;
        if ($cachingEnabled) {
            $events = cache()->remember(
                key: $display->getEventsCacheKey(),
                ttl: now()->addMinutes(15),
                callback: fn() => $this->getAllEvents($display)
            );
        } else {
            $events = $this->getAllEvents($display);
        }

        return $events;
    }

    /**
     * Book a room for a given duration. Handles all business logic.
     * If the connected account has write permissions, creates the event via API.
     * Otherwise, creates a custom event locally.
     * Throws exception if not allowed.
     */
    public function bookRoom(string $displayId, string $userId, int $duration, ?string $summary = null): Event
    {
        $start = now();
        $end = $start->copy()->addMinutes($duration);

        // Check for any conflicting events (both custom and external)
        if ($this->hasConflictingEvents($displayId, $start, $end)) {
            throw new Exception('Cannot book room: there are conflicting events during this time period');
        }

        $display = Display::query()
            ->with(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room'])
            ->findOrFail($displayId);
        $calendar = $display->calendar;

        // Check if we have write permissions and can create via API
        if ($calendar) {
            $hasWritePermissions = false;
            $account = null;

            // Check Outlook account
            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $account = $calendar->outlookAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }
            // Check Google account
            elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $account = $calendar->googleAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }
            // Check CalDAV account
            elseif ($calendar->caldav_account_id && $calendar->caldavAccount) {
                $account = $calendar->caldavAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }

            // If we have write permissions, create event via API
            if ($hasWritePermissions && $account) {
                try {
                    $externalEventId = null;

                    // Create event via Outlook API
                    if ($calendar->outlook_account_id) {
                        $eventData = $this->outlookService->createEvent(
                            $calendar->outlookAccount,
                            $calendar,
                            $summary ?? __('Reserved'),
                            $start,
                            $end
                        );
                        $externalEventId = $eventData['id'] ?? null;
                    }
                    // Create event via Google API
                    elseif ($calendar->google_account_id) {
                        $googleEvent = $this->googleService->createEvent(
                            $calendar->googleAccount,
                            $calendar->calendar_id,
                            $summary ?? __('Reserved'),
                            $start,
                            $end
                        );
                        $externalEventId = $googleEvent?->getId();
                    }
                    // Create event via CalDAV API
                    elseif ($calendar->caldav_account_id) {
                        $externalEventId = $this->caldavService->createEvent(
                            $calendar->caldavAccount,
                            $calendar->calendar_id,
                            $summary ?? __('Reserved'),
                            $start,
                            $end
                        );
                    }

                    // Clear cache to force refetch
                    Cache::forget($display->getEventsCacheKey());

                    // Refetch events to sync the new event
                    $this->syncAllExternalEventsForDisplay($display);

                    // Find the newly created event by external_id
                    // The times might differ slightly due to timezone conversions, so we match by external_id first
                    $event = Event::query()
                        ->where('display_id', $displayId)
                        ->where('external_id', $externalEventId)
                        ->first();

                    if ($event) {
                        return $event;
                    }
                } catch (\Exception $e) {
                    // If API creation fails, fall back to custom event
                    logger()->warning('Failed to create event via API, falling back to custom event', [
                        'error' => $e->getMessage(),
                        'display_id' => $displayId,
                    ]);
                }
            }
        }

        // Fall back to creating a custom event (no write permissions or API creation failed)
        return Event::create([
            'display_id' => $displayId,
            'user_id' => $userId,
            'status' => EventStatus::CONFIRMED,
            'source' => EventSource::CUSTOM,
            'start' => $start,
            'end' => $end,
            'summary' => $summary ?? __('Reserved'),
            'timezone' => config('app.timezone', 'UTC'),
        ]);
    }

    /**
     * Cancel an event. If the event was created via API and account has write permissions,
     * deletes it from the external calendar. Otherwise, marks it as cancelled.
     */
    public function cancelEvent(string $eventId, string $displayId): void
    {
        $event = Event::query()
            ->where('display_id', $displayId)
            ->with(['display.calendar.outlookAccount', 'display.calendar.googleAccount', 'display.calendar.caldavAccount', 'display.calendar.room'])
            ->find($eventId);

        if (!$event) {
            throw new Exception('Event not found or not accessible');
        }

        $display = $event->display;
        $calendar = $display->calendar;

        // If event has external_id and calendar has write permissions, delete via API
        if ($event->external_id && $calendar) {
            $hasWritePermissions = false;
            $account = null;

            // Check Outlook account
            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $account = $calendar->outlookAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }
            // Check Google account
            elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $account = $calendar->googleAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }
            // Check CalDAV account
            elseif ($calendar->caldav_account_id && $calendar->caldavAccount) {
                $account = $calendar->caldavAccount;
                $hasWritePermissions = $account->permission_type === PermissionType::WRITE;
            }

            // If we have write permissions, delete event via API
            if ($hasWritePermissions && $account) {
                try {
                    // Delete event via Outlook API
                    if ($calendar->outlook_account_id) {
                        $this->outlookService->deleteEvent(
                            $calendar->outlookAccount,
                            $calendar,
                            $event->external_id
                        );
                    }
                    // Delete event via Google API
                    elseif ($calendar->google_account_id) {
                        $this->googleService->deleteEvent(
                            $calendar->googleAccount,
                            $calendar->calendar_id,
                            $event->external_id
                        );
                    }
                    // Delete event via CalDAV API
                    elseif ($calendar->caldav_account_id) {
                        $this->caldavService->deleteEvent(
                            $calendar->caldavAccount,
                            $calendar->calendar_id,
                            $event->external_id
                        );
                    }

                    // Clear cache to force refetch
                    Cache::forget($display->getEventsCacheKey());

                    // Refetch events to sync the deletion
                    $this->syncAllExternalEventsForDisplay($display);

                    // Delete the event from database (it will be removed by sync if still exists externally)
                    $event->delete();
                    return;
                } catch (\Exception $e) {
                    // If API deletion fails, fall back to marking as cancelled
                    logger()->warning('Failed to delete event via API, marking as cancelled', [
                        'error' => $e->getMessage(),
                        'event_id' => $eventId,
                        'display_id' => $displayId,
                    ]);
                }
            }
        }

        // Fall back to marking as cancelled (for custom events or if API deletion failed)
        $event->update(['status' => EventStatus::CANCELLED]);
    }

    /**
     * @throws Exception
     */
    private function getAllEvents(Display $display): Collection
    {
        // Make sure external events are up to date
        $this->syncAllExternalEventsForDisplay($display);

        // Then query all events
        return Event::query()
            ->where('display_id', $display->id)
            ->where('start', '>=', $display->getStartTime())
            ->where('start', '<', $display->getEndTime())
            ->orderBy('start')
            ->get();
    }

    /**
     * @throws Exception
     */
    private function syncAllExternalEventsForDisplay(Display $display): void
    {
        $calendar = $display->calendar()
            ->with(['googleAccount', 'outlookAccount', 'caldavAccount', 'room'])
            ->first();

        // Handle Google integration
        if ($calendar->google_account_id) {
            $googleEvents = $this->fetchGoogleEvents($calendar, $display);
            $this->syncExternalEvents($display, EventSource::GOOGLE, $googleEvents);
        }

        // Handle Outlook integration
        if ($calendar->outlook_account_id) {
            $outlookEvents = $this->fetchOutlookEvents($calendar, $display);
            $this->syncExternalEvents($display, EventSource::OUTLOOK, $outlookEvents);
        }

        // Handle CalDAV integration
        if ($calendar->caldav_account_id) {
            $caldavEvents = $this->fetchCalDAVEvents($calendar, $display);
            $this->syncExternalEvents($display, EventSource::CALDAV, $caldavEvents);
        }
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
     * @throws Exception
     */
    private function fetchOutlookEvents(Calendar $calendar, Display $display): Collection
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

        return collect($events)->map(fn($e) => $this->sanitizeOutlookEvent($e));
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
     * @throws \Exception
     */
    private function fetchGoogleEvents(Calendar $calendar, Display $display): Collection
    {
        $events = $this->googleService->fetchEvents(
            googleAccount: $calendar->googleAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $display->getStartTime(),
            endDateTime: $display->getEndTime(),
        );

        return collect($events)->map(fn($e) => $this->sanitizeGoogleEvent($e));
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
     * @throws Exception
     */
    private function fetchCalDAVEvents(Calendar $calendar, Display $display): Collection
    {
        $events = $this->caldavService->fetchEvents(
            caldavAccount: $calendar->caldavAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $display->getStartTime(),
            endDateTime: $display->getEndTime(),
        );

        return collect($events)->map(fn($e) => $this->sanitizeCalDAVEvent($e));
    }

    /**
     * @param array $outlookEvent
     * @return array
     */
    public function sanitizeOutlookEvent(array $outlookEvent): array
    {
        $summary = $this->cleanSubject($outlookEvent['subject']);

        $description = $this->cleanBody(
            Arr::has($outlookEvent, 'body') && is_array($outlookEvent['body']) ?
                $outlookEvent['body']['content'] :
                $outlookEvent['bodyPreview']
        );

        // Get location if available
        $location = $outlookEvent['location']['displayName'] ?? '';

        // Handle all-day event
        $isAllDay = $outlookEvent['isAllDay'] ?? false;

        // Extract date for all-day events, or dateTime with timeZone for regular events
        $start = $isAllDay ? ['dateTime' => explode('T', $outlookEvent['start']['dateTime'])[0]]
            : ['dateTime' => $outlookEvent['start']['dateTime'], 'timeZone' => $outlookEvent['start']['timeZone']];

        $end = $isAllDay ? ['dateTime' => explode('T', $outlookEvent['end']['dateTime'])[0]]
            : ['dateTime' => $outlookEvent['end']['dateTime'], 'timeZone' => $outlookEvent['end']['timeZone']];

        return [
            'id' => $outlookEvent['id'],
            'summary' => $summary,
            'location' => $location,
            'description' => $description,
            'start' => $start['dateTime'],
            'end' => $end['dateTime'],
            'timezone' => $outlookEvent['start']['timeZone'] ?? $outlookEvent['end']['timeZone'] ?? 'UTC',
            'isAllDay' => $isAllDay
        ];
    }

    /**
     * @param GoogleEvent $googleEvent
     * @return array
     */
    public function sanitizeGoogleEvent(GoogleEvent $googleEvent): array
    {
        $start = $googleEvent->getStart();
        $end = $googleEvent->getEnd();

        // Handle all-day event - Google Calendar uses 'date' field for all-day events
        $isAllDay = $start->getDate() !== null;

        return [
            'id' => $googleEvent->getId(),
            'summary' => $this->cleanSubject($googleEvent->getSummary()),
            'location' => $googleEvent->getLocation(),
            'description' => $googleEvent->getDescription(),
            'start' => $isAllDay ? $start->getDate() : $start->getDateTime(),
            'end' => $isAllDay ? $end->getDate() : $end->getDateTime(),
            'timezone' => $start->getTimeZone() ?? $end->getTimeZone() ?? 'UTC',
            'isAllDay' => $isAllDay
        ];
    }

    /**
     * @param array $caldavEvent
     * @return array
     */
    public function sanitizeCalDAVEvent(array $caldavEvent): array
    {
        return [
            'id' => $caldavEvent['id'],
            'summary' => $this->cleanSubject($caldavEvent['summary']),
            'location' => $caldavEvent['location'],
            'description' => $this->cleanBody($caldavEvent['description']),
            'start' => $caldavEvent['start'],
            'end' => $caldavEvent['end'],
            'timezone' => $caldavEvent['timezone'],
            'isAllDay' => $caldavEvent['isAllDay']
        ];
    }

    private function cleanSubject(?string $subject): string
    {
        // Ensure variable is set
        $subject ??= "";

        return trim($subject); // Basic cleanup, can be expanded if necessary
    }

    private function cleanBody(?string $body): string
    {
        // Ensure variable is set
        $body ??= "";

        // Replace newlines and carriage returns as in JS version
        $body = str_replace("\r", "\n", $body);
        return str_replace("\n", ' ', $body);
    }

    /**
     * Check if there are any conflicting events for a display in a given time range.
     *
     * @param string $displayId
     * @param Carbon $start
     * @param Carbon $end
     * @return bool
     */
    public function hasConflictingEvents(string $displayId, Carbon $start, Carbon $end): bool
    {
        return Event::query()
            ->where('display_id', $displayId)
            ->where('status', '!=', EventStatus::CANCELLED)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start', [$start, $end])
                  ->orWhereBetween('end', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start', '<=', $start)->where('end', '>=', $end);
                  });
            })
            ->exists();
    }

    /**
     * Sync external events to the database for a display and source.
     *
     * @param Display $display
     * @param string $source
     * @param Collection $externalEvents
     */
    public function syncExternalEvents(Display $display, string $source, Collection $externalEvents): void
    {
        $existing = Event::query()
            ->where('display_id', $display->id)
            ->where('source', $source)
            ->get()
            ->keyBy('external_id');

        $seenIds = [];

        $externalEvents = $externalEvents->filter(fn ($event) => ! $event['isAllDay']);
        foreach ($externalEvents as $ext) {
            $externalId = $ext['id'];
            $seenIds[] = $externalId;

            $event = $existing->get($externalId) ?? new Event([
                'display_id' => $display->id,
                'user_id' => $display->user_id,
                'source' => $source,
                'external_id' => $externalId,
                'status' => EventStatus::CONFIRMED
            ]);

            // Parse datetime strings and convert to UTC for storage
            // Carbon will automatically parse the timezone from the string and convert to UTC
            $event->start = Carbon::parse($ext['start'])->utc();
            $event->end = Carbon::parse($ext['end'])->utc();
            $event->summary = $ext['summary'];
            $event->description = $ext['description'];
            $event->location = $ext['location'];
            $event->timezone = $ext['timezone'];

            $event->save();
        }

        // Delete events that no longer exist externally
        Event::query()
            ->where('display_id', $display->id)
            ->where('source', $source)
            ->whereNotIn('external_id', $seenIds)
            ->delete();
    }

    public function checkInToEvent(string $eventId, string $displayId): void
    {
        $event = Event::query()
            ->where('display_id', $displayId)
            ->find($eventId);

        if (!$event) {
            throw new Exception('Event not found or not accessible');
        }

        // Only allow check-in if not already checked in
        if ($event->checked_in_at) {
            throw new Exception('Already checked in');
        }

        $event->checkIn();
    }

    private function processExpiredCheckIns(Display $display): void
    {
        if (! $display->isCheckInEnabled()) {
            return;
        }

        $gracePeriod = $display->getCheckInGracePeriod();
        $events = Event::query()
            ->select('id')
            ->where('display_id', $display->id)
            ->whereNull('checked_in_at')
            ->where('start', '<', now()->subMinutes($gracePeriod))
            ->where('status', '!=', EventStatus::CANCELLED)
            ->get();

        if ($events->isNotEmpty()) {
            $events->each->update(['status' => EventStatus::CANCELLED]);
        }
    }
}
