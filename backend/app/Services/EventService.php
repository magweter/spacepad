<?php

namespace App\Services;

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\OutlookBookingMethod;
use App\Enums\PermissionType;
use App\Helpers\DisplaySettings;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\Event;
use App\Support\LocalDay;
use Exception;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService,
    ) {}

    /**
     * Fetch events for a display, without storing external events in the database.
     *
     * @param  Display|string  $display  Display model (ideally with event_subscriptions_count loaded) or display ID.
     *
     * @throws Exception
     */
    public function getEventsForDisplay($display, ?Carbon $forDate = null, ?LocalDay $day = null): Collection
    {
        $display = Display::query()
            ->withCount(['eventSubscriptions' => function ($query) {
                // Only count active subscriptions (exclude pending retry placeholders)
                $query->where('subscription_id', 'not like', 'pending_%');
            }])
            ->findOrFail($display);

        // When fetching for a specific date, skip caching and side-effects.
        if ($forDate !== null) {
            if ($day === null) {
                // The caller did not state its timezone (an older app build). Keep the previous
                // contract: hand back a day either side and let the client clamp, so nothing near
                // its local midnight goes missing.
                return $this->getAllEvents(
                    $display,
                    $forDate->copy()->subDay()->startOfDay(),
                    $forDate->copy()->addDay()->endOfDay()
                );
            }

            [$fetchStart, $fetchEnd] = $day->fetchRange();

            return $this->clampToDay($this->getAllEvents($display, $fetchStart, $fetchEnd), $day);
        }

        // For the status screen the day always matters: a client that does not state one gets the
        // server's day rather than a wide range, because anything beyond today reads on the tablet
        // as if it were happening now.
        $day ??= LocalDay::serverDay(Carbon::now());

        // Update last sync timestamp
        $display->updateLastSyncAt();

        // Release DB events (custom / tablet bookings) that have not been checked in
        $this->processExpiredCheckIns($display);

        // Cache events if caching is enabled and the display has an active event subscription.
        // Displays with active webhook subscriptions use a long TTL (15 min) — the webhook
        // will invalidate the cache when events change.
        // Displays without active subscriptions (no webhook) use a short TTL (2 min) to
        // prevent hammering the Microsoft 365 / Google API on every page load for boards
        // that include many displays, while still staying reasonably fresh.
        $cacheEnabled = config('services.events.cache_enabled');
        if ($cacheEnabled && $display->event_subscriptions_count > 0) {
            $events = cache()->remember(
                key: $display->getEventsCacheKey(),
                ttl: $this->jitteredCacheTtl(15),
                callback: function () use ($display) {
                    logger()->info('Fetching events from API (cache miss)', [
                        'display_id' => $display->id,
                        'display_name' => $display->name,
                    ]);

                    return $this->getAllEvents($display);
                }
            );
        } elseif ($cacheEnabled) {
            $events = cache()->remember(
                key: $display->getEventsCacheKey().':fallback',
                ttl: $this->jitteredCacheTtl(2),
                callback: function () use ($display) {
                    logger()->info('Fetching events from API (no event subscription)', [
                        'display_id' => $display->id,
                        'display_name' => $display->name,
                    ]);

                    return $this->getAllEvents($display);
                }
            );
        } else {
            logger()->info('Fetching events from API (caching disabled)', [
                'display_id' => $display->id,
                'display_name' => $display->name,
            ]);

            $events = $this->getAllEvents($display);
        }

        // The cached collection deliberately spans more than a day so tablets in different
        // timezones can share it; narrowing to the caller's day happens here, per request.
        return $this->clampToDay($events, $day);
    }

    /**
     * A cache TTL with up to 20% of extra spread added on top.
     *
     * The TTL is anchored to the moment of the cache miss, so displays that miss together stay
     * phase-locked: the whole fleet re-expires inside the same window, misses together again, and
     * sets the same TTL once more. One event that empties every entry at once — a cache flush, or a
     * restart on a non-persistent store — is enough to enter that state, and nothing pulls it apart
     * again, so every cycle from then on lands as a burst of external calendar calls.
     *
     * The extra seconds are per entry, which is what breaks the lock: each cycle spreads the fleet
     * a little further apart until the misses are distributed across the whole interval.
     */
    private function jitteredCacheTtl(int $minutes): Carbon
    {
        $spread = (int) ceil($minutes * 60 * 0.2);

        return now()->addMinutes($minutes)->addSeconds(random_int(0, $spread));
    }

    /**
     * Keep only the events that touch the given day.
     *
     * Overlap rather than containment: a meeting running across midnight belongs to both days, and
     * dropping it would make a room look free while it is in use.
     */
    private function clampToDay(Collection $events, LocalDay $day): Collection
    {
        return $events
            ->filter(fn (Event $event) => $day->overlaps($event->start, $event->end))
            ->values();
    }

    /**
     * Book a room for a given duration. Handles all business logic.
     * If the connected account has write permissions, creates the event via API.
     * Otherwise, creates a custom event locally.
     * Throws exception if not allowed.
     */
    public function bookRoom(string $displayId, string $userId, string $summary, ?int $duration = null, ?Carbon $start = null, ?Carbon $end = null, ?string $description = null, array $attendees = []): Event
    {
        // Normalize summary: trim and replace empty with default
        $summary = trim($summary);
        if (empty($summary)) {
            $summary = __('Reserved');
        }

        // Validate duration if provided
        if ($duration !== null) {
            if (! is_int($duration) || $duration <= 0) {
                throw new Exception('Duration must be a positive integer greater than 0');
            }
            $start = now();
            $end = $start->copy()->addMinutes($duration);
        } else {
            // Validate that both start and end are provided
            if ($start === null || $end === null) {
                throw new Exception('Either duration or both start and end times must be provided');
            }
            // Validate that start is before end
            if (! $start->lt($end)) {
                throw new Exception('Start time must be before end time');
            }
        }

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
                            $summary,
                            $start,
                            $end,
                            $description,
                            $attendees
                        );
                        $externalEventId = $eventData['id'] ?? null;
                    }
                    // Create event via Google API
                    elseif ($calendar->google_account_id) {
                        $googleEvent = $this->googleService->createEvent(
                            $calendar->googleAccount,
                            $calendar,
                            $summary,
                            $start,
                            $end,
                            $description,
                            $attendees
                        );
                        $externalEventId = $googleEvent?->getId();
                    }
                    // Create event via CalDAV API
                    elseif ($calendar->caldav_account_id) {
                        $externalEventId = $this->caldavService->createEvent(
                            $calendar->caldavAccount,
                            $calendar->calendar_id,
                            $summary,
                            $start,
                            $end,
                            $description,
                            $attendees
                        );
                    }

                    // Validate that external event ID was returned
                    if (! is_string($externalEventId) || $externalEventId === '') {
                        throw new Exception('External event was created but no external ID was returned. Cannot track or cancel this event.');
                    }

                    // Clear cache to force refetch on next request
                    $this->clearEventsCache($display);

                    // Create a DB row to track this tablet booking (needed for isTabletBooking() and cancellation)
                    // calendar_id is set to mark as a tablet booking
                    $event = DB::transaction(function () use ($displayId, $userId, $calendar, $externalEventId, $start, $end, $summary, $description) {
                        return Event::create([
                            'display_id' => $displayId,
                            'user_id' => $userId,
                            'calendar_id' => $calendar->id,
                            'external_id' => $externalEventId,
                            'status' => EventStatus::CONFIRMED,
                            'source' => $calendar->google_account_id ? EventSource::GOOGLE : ($calendar->outlook_account_id ? EventSource::OUTLOOK : EventSource::CALDAV),
                            'start' => $start,
                            'end' => $end,
                            'summary' => $summary,
                            'description' => $description,
                            'timezone' => config('app.timezone', 'UTC'),
                        ]);
                    });

                    // Wait for Google Calendar API to reflect the change (with retry logic)
                    if ($calendar->google_account_id) {
                        $this->waitForEventInApi($calendar, $externalEventId, $start, $end, true);
                    }

                    return $event;
                } catch (Exception $e) {
                    logger()->error('Failed to create external event or track it in database', [
                        'error' => $e->getMessage(),
                        'display_id' => $displayId,
                        'start' => $start->toIso8601String(),
                        'end' => $end->toIso8601String(),
                    ]);
                    throw $e;
                }
            }
        }

        // Fall back to creating a custom event (no write permissions)
        $fullDescription = $description;
        if (! empty($attendees)) {
            $attendeeList = implode(', ', $attendees);
            $fullDescription = $fullDescription
                ? $fullDescription."\n\nAttendees: ".$attendeeList
                : 'Attendees: '.$attendeeList;
        }

        return Event::create([
            'display_id' => $displayId,
            'user_id' => $userId,
            'status' => EventStatus::CONFIRMED,
            'source' => EventSource::CUSTOM,
            'start' => $start,
            'end' => $end,
            'summary' => $summary,
            'description' => $fullDescription,
            'timezone' => config('app.timezone', 'UTC'),
        ]);
    }

    /**
     * Cancel an event. DB events (custom / tablet bookings) are deleted or cancelled via API.
     * External events are deleted via API if write permission exists, otherwise hidden via Redis.
     */
    public function cancelEvent(string $eventId, string $displayId): void
    {
        $display = Display::query()
            ->with(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room', 'settings'])
            ->findOrFail($displayId);

        // Check cancel permission setting first
        $cancelPermission = DisplaySettings::getCancelPermission($display);
        if ($cancelPermission === 'none') {
            throw new Exception('Cancelling events is not allowed on this display');
        }

        // Try to find as a DB event (custom event or tablet booking)
        $event = Event::query()
            ->where('display_id', $displayId)
            ->find($eventId);

        if ($event) {
            if ($cancelPermission === 'tablet_only' && ! $event->isTabletBooking()) {
                throw new Exception('Only events booked via this tablet can be cancelled');
            }
            $this->cancelDbEvent($event, $display);

            return;
        }

        // External event (not in DB): $eventId is the external calendar ID
        if ($cancelPermission === 'tablet_only') {
            // External events are never tablet bookings
            throw new Exception('Only events booked via this tablet can be cancelled');
        }

        $this->cancelExternalEvent($eventId, $display);
    }

    /**
     * Check in to an event. DB events update the checked_in_at column.
     * External events store check-in state in Redis.
     */
    public function checkInToEvent(string $eventId, string $displayId): void
    {
        // Check DB first (custom events and tablet bookings)
        $event = Event::query()
            ->where('display_id', $displayId)
            ->find($eventId);

        if ($event) {
            if ($event->checked_in_at) {
                throw new Exception('Already checked in');
            }
            $event->checkIn();

            return;
        }

        // External event — store check-in state in Redis
        if ($this->getCheckInState($displayId, $eventId)) {
            throw new Exception('Already checked in');
        }

        $this->storeCheckInState($displayId, $eventId);

        // Invalidate cache so next fetch reflects the check-in
        $display = Display::find($displayId);
        if ($display) {
            $this->clearEventsCache($display);
        }
    }

    /**
     * Extend the end time of the current event by adding minutes.
     * Accepts new_end as an absolute UTC timestamp from the client.
     */
    public function extendEvent(string $eventId, string $displayId, Carbon $newEnd): void
    {
        $display = Display::query()
            ->with(['calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room', 'settings'])
            ->findOrFail($displayId);

        if (! DisplaySettings::isExtendEnabled($display)) {
            throw new Exception('Extending events is not allowed on this display', 403);
        }

        $event = Event::query()
            ->where('display_id', $displayId)
            ->find($eventId);

        if ($event) {
            $this->extendDbEvent($event, $display, $newEnd);

            return;
        }

        // External event (not in DB) — requires write permission
        $this->extendExternalEvent($eventId, $display, $newEnd);
    }

    private function extendDbEvent(Event $event, Display $display, Carbon $newEnd): void
    {
        $calendar = $display->calendar;

        if ($event->external_id && $calendar) {
            $hasWritePermissions = false;

            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $hasWritePermissions = $calendar->outlookAccount->permission_type === PermissionType::WRITE;
            } elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $hasWritePermissions = $calendar->googleAccount->permission_type === PermissionType::WRITE;
            }

            if ($hasWritePermissions) {
                try {
                    if ($calendar->outlook_account_id) {
                        $this->outlookService->patchEventEndTime($calendar->outlookAccount, $calendar, $event->external_id, $newEnd);
                    } elseif ($calendar->google_account_id) {
                        $this->googleService->patchEventEndTime($calendar->googleAccount, $calendar, $event->external_id, $newEnd);
                    }
                } catch (Exception $e) {
                    logger()->warning('Failed to update DB event end time via API', [
                        'error' => $e->getMessage(),
                        'event_id' => $event->id,
                    ]);
                }
            }
        }

        $event->update(['end' => $newEnd]);
        $this->markEventExtended($display->id, [$event->id, $event->external_id], $newEnd);
        $this->clearEventsCache($display);
    }

    private function extendExternalEvent(string $externalId, Display $display, Carbon $newEnd): void
    {
        $calendar = $display->calendar;

        if (! $calendar) {
            throw new Exception('No calendar linked to this display', 400);
        }

        $hasWritePermissions = false;

        if ($calendar->outlook_account_id && $calendar->outlookAccount) {
            $hasWritePermissions = $calendar->outlookAccount->permission_type === PermissionType::WRITE;
        } elseif ($calendar->google_account_id && $calendar->googleAccount) {
            $hasWritePermissions = $calendar->googleAccount->permission_type === PermissionType::WRITE;
        }

        if (! $hasWritePermissions) {
            throw new Exception('Cannot extend this event: write permission is required', 403);
        }

        if ($calendar->outlook_account_id) {
            $this->outlookService->patchEventEndTime($calendar->outlookAccount, $calendar, $externalId, $newEnd);
        } elseif ($calendar->google_account_id) {
            $this->googleService->patchEventEndTime($calendar->googleAccount, $calendar, $externalId, $newEnd);
        }

        $this->markEventExtended($display->id, [$externalId], $newEnd);
        $this->clearEventsCache($display);
    }

    /**
     * Check if there are any conflicting events for a display in a given time range.
     */
    public function hasConflictingEvents(string $displayId, Carbon $start, Carbon $end): bool
    {
        $display = Display::findOrFail($displayId);

        // Use cached events if available to avoid redundant API calls during booking
        $events = Cache::get($display->getEventsCacheKey()) ?? $this->getAllEvents($display);

        return $events->contains(function ($event) use ($start, $end) {
            $eStart = $event->start;
            $eEnd = $event->end;

            return ($eStart >= $start && $eStart < $end)
                || ($eEnd > $start && $eEnd <= $end)
                || ($eStart < $start && $eEnd > $end);
        });
    }

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

        // Extract date for all-day events, or dateTime for regular events.
        // With the Prefer: outlook.timezone="UTC" header, Outlook returns times as UTC
        // strings without a timezone suffix (e.g. "2026-05-05T07:00:00.0000000").
        // We strip any existing trailing Z or fractional seconds suffix, then add 'Z'
        // so Carbon always parses them unambiguously as UTC.
        if ($isAllDay) {
            $startDateStr = explode('T', $outlookEvent['start']['dateTime'])[0];
            $endDateStr = explode('T', $outlookEvent['end']['dateTime'])[0];
        } else {
            // Strip trailing Z if present, then re-add it to normalise the format
            $startDateStr = rtrim($outlookEvent['start']['dateTime'], 'Z').'Z';
            $endDateStr = rtrim($outlookEvent['end']['dateTime'], 'Z').'Z';
        }

        // Extract Teams join URL: prefer onlineMeeting.joinUrl (current), fall back to
        // deprecated onlineMeetingUrl, then regex extraction from the event body
        $joinUrl = $outlookEvent['onlineMeeting']['joinUrl']
            ?? $outlookEvent['onlineMeetingUrl']
            ?? $this->extractMeetingUrl($description);

        return [
            'id' => $outlookEvent['id'],
            'summary' => $summary,
            'location' => $location,
            'description' => $description,
            'join_url' => $joinUrl,
            'organizer_name' => $outlookEvent['organizer']['emailAddress']['name'] ?? null,
            'start' => $startDateStr,
            'end' => $endDateStr,
            'timezone' => 'UTC',
            'isAllDay' => $isAllDay,
        ];
    }

    public function sanitizeGoogleEvent(GoogleEvent $googleEvent): array
    {
        $start = $googleEvent->getStart();
        $end = $googleEvent->getEnd();

        // Handle all-day event - Google Calendar uses 'date' field for all-day events
        $isAllDay = $start->getDate() !== null;

        $description = $googleEvent->getDescription();
        $joinUrl = $googleEvent->getHangoutLink() ?? $this->extractMeetingUrl($description);

        $organizer = $googleEvent->getOrganizer();

        return [
            'id' => $googleEvent->getId(),
            'summary' => $this->cleanSubject($googleEvent->getSummary()),
            'location' => $googleEvent->getLocation(),
            'description' => $description,
            'join_url' => $joinUrl,
            'organizer_name' => $organizer?->getDisplayName() ?? $organizer?->getEmail() ?? null,
            'start' => $isAllDay ? $start->getDate() : $start->getDateTime(),
            'end' => $isAllDay ? $end->getDate() : $end->getDateTime(),
            'timezone' => $start->getTimeZone() ?? $end->getTimeZone() ?? 'UTC',
            'isAllDay' => $isAllDay,
        ];
    }

    public function sanitizeCalDAVEvent(array $caldavEvent): array
    {
        $description = $this->cleanBody($caldavEvent['description']);
        $joinUrl = $this->extractMeetingUrl($caldavEvent['location'] ?? '')
            ?? $this->extractMeetingUrl($description);

        return [
            'id' => $caldavEvent['id'],
            'summary' => $this->cleanSubject($caldavEvent['summary']),
            'location' => $caldavEvent['location'],
            'description' => $description,
            'join_url' => $joinUrl,
            'organizer_name' => $caldavEvent['organizer_name'] ?? null,
            'start' => $caldavEvent['start'],
            'end' => $caldavEvent['end'],
            'timezone' => $caldavEvent['timezone'],
            'isAllDay' => $caldavEvent['isAllDay'],
        ];
    }

    /**
     * Fetch all events for a display without writing external events to the database.
     * External events from Google/Outlook/CalDAV are returned as transient (unsaved) models.
     * Only custom events and tablet bookings are persisted in the database.
     *
     * @throws Exception
     */
    private function getAllEvents(Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        // This is the range asked of the calendar providers, not what a client gets back: callers
        // narrow the result to their own day (see getEventsForDisplay). Staying a day either side
        // keeps bookings near a far-offset display's local midnight inside the request, and lets
        // one cached collection serve tablets in different timezones.
        //
        // Widening this window is only safe because of that narrowing. When it was widened without
        // it (v1.8.1) the tablet's status screen picked the first event after "now", so tomorrow's
        // first booking showed up as "Next" with just a time and read as if it were today — the
        // reason it was reverted on dev. Every status response now goes through clampToDay(), and a
        // client that does not state its timezone is clamped to the server's day, so that cannot
        // happen again.
        $start = $start ?? now()->subDay()->startOfDay();
        $end = $end ?? now()->addDay()->endOfDay();

        $calendar = $display->calendar()
            ->with(['googleAccount', 'outlookAccount', 'caldavAccount', 'room'])
            ->first();

        logger()->debug('getAllEvents: starting fetch', [
            'display_id' => $display->id,
            'display_name' => $display->name ?? null,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'calendar_id_db' => $calendar?->id,
            'calendar_type' => $calendar ? ($calendar->google_account_id ? 'google' : ($calendar->outlook_account_id ? 'outlook' : ($calendar->caldav_account_id ? 'caldav' : 'unknown'))) : null,
            'calendar_resource_id' => $calendar?->calendar_id,
            'is_room' => $calendar?->room ? true : false,
        ]);

        // Fetch raw event data from external calendar APIs
        $rawExternal = collect();
        if ($calendar?->google_account_id) {
            $rawExternal = $rawExternal->concat(
                $this->fetchGoogleEvents($calendar, $display, $start, $end)
                    ->map(fn ($e) => $e + ['source' => EventSource::GOOGLE])
            );
        }
        if ($calendar?->outlook_account_id) {
            $rawExternal = $rawExternal->concat(
                $this->fetchOutlookEvents($calendar, $display, $start, $end)
                    ->map(fn ($e) => $e + ['source' => EventSource::OUTLOOK])
            );
        }
        if ($calendar?->caldav_account_id) {
            $rawExternal = $rawExternal->concat(
                $this->fetchCalDAVEvents($calendar, $display, $start, $end)
                    ->map(fn ($e) => $e + ['source' => EventSource::CALDAV])
            );
        }

        logger()->debug('getAllEvents: raw external events fetched', [
            'display_id' => $display->id,
            'raw_count' => $rawExternal->count(),
            'all_day_filtered' => $rawExternal->filter(fn ($e) => $e['isAllDay'])->count(),
        ]);

        // Load persisted DB events: custom events and tablet bookings only
        $dbEvents = Event::query()
            ->where('display_id', $display->id)
            ->where(function ($q) {
                $q->where('source', EventSource::CUSTOM)
                    ->orWhereNotNull('calendar_id');
            })
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->where('status', '!=', EventStatus::CANCELLED)
            ->orderBy('start')
            ->get();

        // Build lookup maps so external events can be matched to their DB tablet booking.
        // For admin_consent the external_id stored in DB equals the calendar event ID (exact match).
        // For user_account rooms the stored external_id is the user's calendar event ID while the
        // room's calendarview returns a different ID — fall back to matching by start-time minute.
        $tabletById = $dbEvents->whereNotNull('external_id')->keyBy('external_id');
        $tabletByStart = $dbEvents
            ->filter(fn ($e) => $e->calendar_id !== null)
            ->keyBy(fn ($e) => Carbon::parse($e->start)->utc()->format('Y-m-d H:i'));

        $checkInEnabled = $display->isCheckInEnabled();
        $gracePeriod = $checkInEnabled ? $display->getCheckInGracePeriod() : 0;

        // Build transient Event models from external API data.
        // When an external event matches a DB tablet booking, the DB record's identity (id,
        // calendar_id, external_id) is used so that cancel / extend / check-in operations
        // route to the correct DB row and use the correct external calendar event ID.
        $matchedTabletIds = [];

        $externalModels = $rawExternal
            ->filter(fn ($e) => ! $e['isAllDay'])
            ->filter(fn ($e) => ! $this->isEventReleased($display->id, $e['id']))
            ->map(function ($ext) use ($display, $checkInEnabled, $gracePeriod, $tabletById, $tabletByStart, &$matchedTabletIds) {
                $eventStart = Carbon::parse($ext['start'])->utc();
                $eventEnd = Carbon::parse($ext['end'])->utc();

                $tabletBooking = $tabletById[$ext['id']]
                    ?? $tabletByStart[$eventStart->format('Y-m-d H:i')]
                    ?? null;

                if ($tabletBooking) {
                    $matchedTabletIds[$tabletBooking->id] = true;
                }

                // An event extended moments ago can still come back from the provider with its
                // old end time. Prefer the end we know we wrote, so the tablet shows the new
                // time on its very next refresh instead of after the provider catches up.
                $extendedEnd = $this->getExtendedEnd($display->id, $ext['id'], $tabletBooking?->id);
                if ($extendedEnd && $extendedEnd->gt($eventEnd)) {
                    $eventEnd = $extendedEnd;
                }

                // Grace-period check only applies to pure external events; tablet bookings
                // are handled by processExpiredCheckIns() before we get here.
                if (! $tabletBooking) {
                    $checkedInAt = $this->getCheckInState($display->id, $ext['id']);
                    if ($checkInEnabled && ! $checkedInAt && $eventStart->lt(now()->subMinutes($gracePeriod))) {
                        $this->markEventReleased($display->id, $ext['id'], $eventEnd);

                        return null;
                    }
                } else {
                    $checkedInAt = $tabletBooking->checked_in_at
                        ?? $this->getCheckInState($display->id, $tabletBooking->id);
                }

                $event = new Event;
                if ($tabletBooking) {
                    // Use DB record's identity so operations (cancel, extend, check-in) find it.
                    $event->id = $tabletBooking->id;
                    $event->calendar_id = $tabletBooking->calendar_id;
                    $event->external_id = $tabletBooking->external_id;
                } else {
                    $event->id = $ext['id'];
                    $event->calendar_id = null;
                    $event->external_id = $ext['id'];
                }
                $event->display_id = $display->id;
                $event->user_id = $tabletBooking?->user_id ?? $display->user_id;
                $event->source = $ext['source'];
                $event->status = EventStatus::CONFIRMED;
                $event->summary = $ext['summary'];
                $event->description = $this->truncateDescription($ext['description'] ?? null);
                $event->location = $ext['location'] ?? null;
                $event->join_url = isset($ext['join_url']) ? substr($ext['join_url'], 0, 1000) : null;
                $event->start = $eventStart;
                $event->end = $eventEnd;
                $event->timezone = $ext['timezone'];
                $event->checked_in_at = $checkedInAt;
                $event->organizer_name = $ext['organizer_name'] ?? null;

                return $event;
            })
            ->filter();

        // Include only DB events that were not already represented by an external calendar event.
        $unmatchedDbEvents = $dbEvents->filter(fn ($e) => ! isset($matchedTabletIds[$e->id]));

        return $externalModels->concat($unmatchedDbEvents)->sortBy('start')->values();
    }

    /**
     * Cancel a DB event (custom event or tablet booking).
     */
    private function cancelDbEvent(Event $event, Display $display): void
    {
        $calendar = $display->calendar;

        if ($event->external_id && $calendar) {
            $hasWritePermissions = false;

            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $hasWritePermissions = $calendar->outlookAccount->permission_type === PermissionType::WRITE;
            } elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $hasWritePermissions = $calendar->googleAccount->permission_type === PermissionType::WRITE;
            } elseif ($calendar->caldav_account_id && $calendar->caldavAccount) {
                $hasWritePermissions = $calendar->caldavAccount->permission_type === PermissionType::WRITE;
            }

            if ($hasWritePermissions) {
                try {
                    if ($calendar->outlook_account_id) {
                        $this->outlookService->deleteEvent($calendar->outlookAccount, $calendar, $event->external_id);
                    } elseif ($calendar->google_account_id) {
                        $this->googleService->deleteEvent($calendar->googleAccount, $calendar, $event->external_id);
                    } elseif ($calendar->caldav_account_id) {
                        $this->caldavService->deleteEvent($calendar->caldavAccount, $calendar->calendar_id, $event->external_id);
                    }

                    $this->clearEventsCache($display);

                    if ($calendar->google_account_id) {
                        $this->waitForEventInApi($calendar, $event->external_id, $event->start, $event->end, false);
                    }

                    $event->update(['status' => EventStatus::CANCELLED]);

                    return;
                } catch (Exception $e) {
                    logger()->warning('Failed to delete event via API, marking as cancelled', [
                        'error' => $e->getMessage(),
                        'event_id' => $event->id,
                    ]);
                }
            }
        }

        if ($event->isCustomEvent()) {
            $event->delete();
            $this->clearEventsCache($display);

            return;
        }

        $event->update(['status' => EventStatus::CANCELLED]);
        $this->clearEventsCache($display);
    }

    /**
     * Cancel an external event that is not in the database.
     * Deletes via API if write permission exists, otherwise hides via Redis.
     */
    private function cancelExternalEvent(string $externalId, Display $display): void
    {
        $calendar = $display->calendar;

        if ($calendar) {
            $hasWritePermissions = false;

            if ($calendar->outlook_account_id && $calendar->outlookAccount) {
                $hasWritePermissions = $calendar->outlookAccount->permission_type === PermissionType::WRITE;
            } elseif ($calendar->google_account_id && $calendar->googleAccount) {
                $hasWritePermissions = $calendar->googleAccount->permission_type === PermissionType::WRITE;
            } elseif ($calendar->caldav_account_id && $calendar->caldavAccount) {
                $hasWritePermissions = $calendar->caldavAccount->permission_type === PermissionType::WRITE;
            }

            if ($hasWritePermissions) {
                try {
                    if ($calendar->outlook_account_id) {
                        $this->outlookService->deleteEvent($calendar->outlookAccount, $calendar, $externalId);
                    } elseif ($calendar->google_account_id) {
                        $this->googleService->deleteEvent($calendar->googleAccount, $calendar, $externalId);
                    } elseif ($calendar->caldav_account_id) {
                        $this->caldavService->deleteEvent($calendar->caldavAccount, $calendar->calendar_id, $externalId);
                    }
                    $this->clearEventsCache($display);

                    return;
                } catch (Exception $e) {
                    logger()->warning('Failed to delete external event via API', [
                        'error' => $e->getMessage(),
                        'external_id' => $externalId,
                        'display_id' => $display->id,
                    ]);
                }
            }
        }

        // No write permission or API deletion failed: hide event from display via Redis
        $this->markEventReleased($display->id, $externalId);
        $this->clearEventsCache($display);
    }

    /**
     * @throws Exception
     */
    private function fetchOutlookEvents(Calendar $calendar, Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $start = $start ?? $display->getStartTime();
        $end = $end ?? $display->getEndTime();
        $events = [];

        $outlookAccount = $calendar->outlookAccount;
        logger()->debug('fetchOutlookEvents: starting', [
            'display_id' => $display->id,
            'calendar_resource_id' => $calendar->calendar_id,
            'is_room' => $calendar->room ? true : false,
            'outlook_account_id' => $outlookAccount?->id,
            'outlook_account_email' => $outlookAccount?->email,
            'token_expires_at' => $outlookAccount?->token_expires_at?->toIso8601String(),
            'token_expired' => $outlookAccount ? now()->gt($outlookAccount->token_expires_at) : null,
            'account_status' => $outlookAccount?->status,
            'booking_method' => $outlookAccount?->booking_method?->value,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ]);

        try {
            if ($calendar->room) {
                $useAppOnlyToken = $outlookAccount->booking_method === OutlookBookingMethod::ADMIN_CONSENT;

                $events = $this->outlookService->fetchEventsByUser(
                    outlookAccount: $calendar->outlookAccount,
                    emailAddress: $calendar->calendar_id,
                    startDateTime: $start,
                    endDateTime: $end,
                    useAppOnlyToken: $useAppOnlyToken,
                );
            } else {
                // Non-room calendar: fetch from the user's own named calendar.
                $events = $this->outlookService->fetchEventsByCalendar(
                    outlookAccount: $calendar->outlookAccount,
                    calendarId: $calendar->calendar_id,
                    startDateTime: $start,
                    endDateTime: $end,
                );
            }
        } catch (Exception $e) {
            logger()->warning('Failed to fetch Outlook events, returning empty', [
                'outlook_account_id' => $outlookAccount?->id,
                'calendar_id' => $calendar->calendar_id,
                'display_id' => $display->id,
                'booking_method' => $outlookAccount?->booking_method?->value,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        logger()->debug('fetchOutlookEvents: raw events from API', [
            'display_id' => $display->id,
            'count' => count($events),
            'first_event_summary' => count($events) > 0 ? ($events[0]['subject'] ?? 'no subject') : null,
        ]);

        return collect($events)->map(fn ($e) => $this->sanitizeOutlookEvent($e));
    }

    /**
     * @throws Exception
     */
    private function fetchGoogleEvents(Calendar $calendar, Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        try {
            $events = $this->googleService->fetchEvents(
                googleAccount: $calendar->googleAccount,
                calendarId: $calendar->calendar_id,
                startDateTime: $start ?? $display->getStartTime(),
                endDateTime: $end ?? $display->getEndTime(),
            );
        } catch (Exception $e) {
            logger()->warning('Failed to fetch Google events, returning empty', [
                'google_account_id' => $calendar->googleAccount->id,
                'display_id' => $display->id,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        // Get room email if this calendar has a room
        $roomEmail = $calendar->room?->email_address;

        // Filter out cancelled events and events where the room declined as attendee
        return collect($events)
            ->filter(function ($event) use ($roomEmail) {
                // Filter out cancelled events
                if ($event->getStatus() === 'cancelled') {
                    return false;
                }

                // If this calendar has a room, check if the room declined the event
                if ($roomEmail && $event->getAttendees()) {
                    foreach ($event->getAttendees() as $attendee) {
                        // Check if this attendee is the room and if it declined
                        if (strtolower($attendee->getEmail()) === strtolower($roomEmail)) {
                            $responseStatus = $attendee->getResponseStatus();
                            // Filter out events where the room declined
                            if ($responseStatus === 'declined') {
                                return false;
                            }
                        }
                    }
                }

                return true;
            })
            ->map(fn ($e) => $this->sanitizeGoogleEvent($e));
    }

    /**
     * @throws Exception
     */
    private function fetchCalDAVEvents(Calendar $calendar, Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $events = $this->caldavService->fetchEvents(
            caldavAccount: $calendar->caldavAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $start ?? $display->getStartTime(),
            endDateTime: $end ?? $display->getEndTime(),
        );

        return collect($events)->map(fn ($e) => $this->sanitizeCalDAVEvent($e));
    }

    private function extractMeetingUrl(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        // Decode HTML entities so &amp; becomes & before matching URLs
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $patterns = [
            // Microsoft Teams
            'https://teams\.microsoft\.com/l/meetup-join/[^\s<>"\']+',
            // Microsoft Teams (short link / webinar)
            'https://teams\.live\.com/meet/[^\s<>"\']+',
            // Zoom
            'https://[a-z0-9]+\.zoom\.us/j/[^\s<>"\']+',
            // Google Meet
            'https://meet\.google\.com/[a-z\-]+',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match('#'.$pattern.'#i', $text, $matches)) {
                return rtrim($matches[0], '.,;)');
            }
        }

        return null;
    }

    private function cleanSubject(?string $subject): string
    {
        $subject ??= '';

        return trim($subject);
    }

    private function cleanBody(?string $body): string
    {
        $body ??= '';
        $body = str_replace("\r", "\n", $body);

        return str_replace("\n", ' ', $body);
    }

    /**
     * Safely truncate description to prevent database errors.
     * MEDIUMTEXT can hold up to 16MB, but we'll limit to 10MB for safety.
     */
    private function truncateDescription(?string $description): string
    {
        if ($description === null || $description === '') {
            return '';
        }

        $maxLength = 10 * 1024 * 1024; // 10MB in bytes

        if (strlen($description) > $maxLength) {
            return substr($description, 0, $maxLength - 3).'...';
        }

        return $description;
    }

    /**
     * Get the stored check-in time for an external event from Redis.
     */
    private function getCheckInState(string $displayId, string $externalId): ?Carbon
    {
        $value = Cache::get("checkin:{$displayId}:{$externalId}");

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Store check-in time for an external event in Redis (24h TTL).
     */
    private function storeCheckInState(string $displayId, string $externalId): void
    {
        Cache::put("checkin:{$displayId}:{$externalId}", now()->toIso8601String(), now()->addDay());
    }

    /**
     * Check whether an external event has been released (hidden) via Redis.
     */
    private function isEventReleased(string $displayId, string $externalId): bool
    {
        return Cache::has("released:{$displayId}:{$externalId}");
    }

    /**
     * Mark an external event as released (hidden from display) via Redis.
     */
    private function markEventReleased(string $displayId, string $externalId, ?Carbon $eventEnd = null): void
    {
        // Keep the released flag until the event has ended + 1 hour buffer
        $ttl = $eventEnd ? max(0, $eventEnd->timestamp - now()->timestamp) + 3600 : 86400;
        Cache::put("released:{$displayId}:{$externalId}", true, $ttl);
    }

    /**
     * Remember the end time we just wrote for an extended event.
     *
     * Clearing the events cache alone is not enough: Microsoft Graph and Google Calendar are
     * eventually consistent, so the re-fetch that happens milliseconds later can still return
     * the old end time — and that stale value would then be cached again for the full TTL.
     * Keyed by every identifier the event can surface under (DB row id and external id),
     * because tablet bookings are matched back to their external copy by either.
     */
    private function markEventExtended(string $displayId, array $eventKeys, Carbon $newEnd): void
    {
        foreach (array_filter($eventKeys) as $key) {
            Cache::put("extended:{$displayId}:{$key}", $newEnd->toIso8601String(), now()->addMinutes(2));
        }
    }

    /**
     * Get the end time of a just-extended event, if it was extended within the last 2 minutes.
     */
    private function getExtendedEnd(string $displayId, ?string ...$eventKeys): ?Carbon
    {
        foreach (array_filter($eventKeys) as $key) {
            $value = Cache::get("extended:{$displayId}:{$key}");

            if ($value) {
                return Carbon::parse($value);
            }
        }

        return null;
    }

    /**
     * Wait for an event to appear or disappear in Google Calendar API.
     * Retries with exponential backoff to handle Google's eventual consistency.
     */
    private function waitForEventInApi(Calendar $calendar, string $externalEventId, Carbon $start, Carbon $end, bool $shouldExist): void
    {
        if (! $calendar->google_account_id || ! $calendar->googleAccount) {
            return; // Only wait for Google Calendar API
        }

        $maxAttempts = 5;
        $baseDelay = 0.5; // Start with 500ms

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $googleEvents = $this->googleService->fetchEvents(
                    $calendar->googleAccount,
                    $calendar->calendar_id,
                    $start->copy()->subHours(1),
                    $end->copy()->addHours(1)
                );

                $eventExists = false;
                foreach ($googleEvents as $googleEvent) {
                    if ($googleEvent->getId() === $externalEventId) {
                        $eventExists = true;
                        break;
                    }
                }

                if ($eventExists === $shouldExist) {
                    return;
                }

                if ($attempt === $maxAttempts) {
                    logger()->warning('Event state in Google API did not match expected state after retries', [
                        'external_event_id' => $externalEventId,
                        'expected_exists' => $shouldExist,
                        'actual_exists' => $eventExists,
                        'attempts' => $maxAttempts,
                    ]);

                    return;
                }

                $delay = $baseDelay * pow(2, $attempt - 1);
                usleep((int) ($delay * 1000000));

            } catch (Exception $e) {
                logger()->warning('Error checking event in Google API during wait', [
                    'error' => $e->getMessage(),
                    'external_event_id' => $externalEventId,
                    'attempt' => $attempt,
                ]);

                if ($attempt === $maxAttempts) {
                    return;
                }

                $delay = $baseDelay * pow(2, $attempt - 1);
                usleep((int) ($delay * 1000000));
            }
        }
    }

    /**
     * Clear all event cache entries for a display (both webhook-backed and fallback).
     */
    private function clearEventsCache(Display $display): void
    {
        Cache::forget($display->getEventsCacheKey());
        Cache::forget($display->getEventsCacheKey().':fallback');
    }

    /**
     * Release DB events (custom and tablet bookings) whose check-in grace period has expired.
     * External events' expiry is handled inline in getAllEvents().
     */
    private function processExpiredCheckIns(Display $display): void
    {
        if (! $display->isCheckInEnabled()) {
            return;
        }

        $gracePeriod = $display->getCheckInGracePeriod();

        // Only handle DB-persisted events (custom events and tablet bookings)
        $events = Event::query()
            ->select('id')
            ->where('display_id', $display->id)
            ->where(function ($q) {
                $q->where('source', EventSource::CUSTOM)
                    ->orWhereNotNull('calendar_id');
            })
            ->whereNull('checked_in_at')
            ->where('start', '<', now()->subMinutes($gracePeriod))
            ->where('status', '!=', EventStatus::CANCELLED)
            ->get();

        if ($events->isNotEmpty()) {
            $events->each->update(['status' => EventStatus::CANCELLED]);
        }
    }
}
