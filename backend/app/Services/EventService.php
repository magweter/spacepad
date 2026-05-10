<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\EventSource;
use App\Enums\PermissionType;
use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\Event;
use App\Models\Calendar;
use Exception;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        protected OutlookService $outlookService,
        protected GoogleService $googleService,
        protected CalDAVService $caldavService,
    ) {
    }

    /**
     * Fetch events for a display, without storing external events in the database.
     * @throws Exception
     */
    public function getEventsForDisplay($display, ?Carbon $forDate = null): Collection
    {
        $display = Display::query()
            ->withCount(['eventSubscriptions' => function ($query) {
                // Only count active subscriptions (exclude pending retry placeholders)
                $query->where('subscription_id', 'not like', 'pending_%');
            }])
            ->findOrFail($display);

        // When fetching for a specific date, skip caching and side-effects
        if ($forDate !== null) {
            $start = $forDate->copy()->startOfDay();
            $end = $forDate->copy()->endOfDay();
            return $this->getAllEvents($display, $start, $end);
        }

        // Update last sync timestamp
        $display->updateLastSyncAt();

        // Release DB events (custom / tablet bookings) that have not been checked in
        $this->processExpiredCheckIns($display);

        // Cache events if caching is enabled and the display has an active event subscription
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
    public function bookRoom(string $displayId, string $userId, string $summary, ?int $duration = null, ?Carbon $start = null, ?Carbon $end = null, ?string $description = null, array $attendees = []): Event
    {
        // Normalize summary: trim and replace empty with default
        $summary = trim($summary);
        if (empty($summary)) {
            $summary = __('Reserved');
        }

        // Validate duration if provided
        if ($duration !== null) {
            if (!is_int($duration) || $duration <= 0) {
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
            if (!$start->lt($end)) {
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
                    if (!is_string($externalEventId) || $externalEventId === '') {
                        throw new Exception('External event was created but no external ID was returned. Cannot track or cancel this event.');
                    }

                    // Clear cache to force refetch on next request
                    Cache::forget($display->getEventsCacheKey());

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
                } catch (\Exception $e) {
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
        if (!empty($attendees)) {
            $attendeeList = implode(', ', $attendees);
            $fullDescription = $fullDescription
                ? $fullDescription . "\n\nAttendees: " . $attendeeList
                : "Attendees: " . $attendeeList;
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
            if ($cancelPermission === 'tablet_only' && !$event->isTabletBooking()) {
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
            Cache::forget($display->getEventsCacheKey());
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

        if (!DisplaySettings::isExtendEnabled($display)) {
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
                } catch (\Exception $e) {
                    logger()->warning('Failed to update DB event end time via API', [
                        'error' => $e->getMessage(),
                        'event_id' => $event->id,
                    ]);
                }
            }
        }

        $event->update(['end' => $newEnd]);
        Cache::forget($display->getEventsCacheKey());
    }

    private function extendExternalEvent(string $externalId, Display $display, Carbon $newEnd): void
    {
        $calendar = $display->calendar;

        if (!$calendar) {
            throw new Exception('No calendar linked to this display', 400);
        }

        $hasWritePermissions = false;

        if ($calendar->outlook_account_id && $calendar->outlookAccount) {
            $hasWritePermissions = $calendar->outlookAccount->permission_type === PermissionType::WRITE;
        } elseif ($calendar->google_account_id && $calendar->googleAccount) {
            $hasWritePermissions = $calendar->googleAccount->permission_type === PermissionType::WRITE;
        }

        if (!$hasWritePermissions) {
            throw new Exception('Cannot extend this event — write permission is required', 403);
        }

        if ($calendar->outlook_account_id) {
            $this->outlookService->patchEventEndTime($calendar->outlookAccount, $calendar, $externalId, $newEnd);
        } elseif ($calendar->google_account_id) {
            $this->googleService->patchEventEndTime($calendar->googleAccount, $calendar, $externalId, $newEnd);
        }

        Cache::forget($display->getEventsCacheKey());
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
            $startDateStr = rtrim($outlookEvent['start']['dateTime'], 'Z') . 'Z';
            $endDateStr = rtrim($outlookEvent['end']['dateTime'], 'Z') . 'Z';
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
            'isAllDay' => $isAllDay
        ];
    }

    /**
     * @param array $caldavEvent
     * @return array
     */
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
            'isAllDay' => $caldavEvent['isAllDay']
        ];
    }

    /**
     * Fetch all events for a display without writing external events to the database.
     * External events from Google/Outlook/CalDAV are returned as transient (unsaved) models.
     * Only custom events and tablet bookings are persisted in the database.
     * @throws Exception
     */
    private function getAllEvents(Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $start = $start ?? $display->getStartTime();
        $end = $end ?? $display->getEndTime();

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
                    ->map(fn($e) => $e + ['source' => EventSource::GOOGLE])
            );
        }
        if ($calendar?->outlook_account_id) {
            $rawExternal = $rawExternal->concat(
                $this->fetchOutlookEvents($calendar, $display, $start, $end)
                    ->map(fn($e) => $e + ['source' => EventSource::OUTLOOK])
            );
        }
        if ($calendar?->caldav_account_id) {
            $rawExternal = $rawExternal->concat(
                $this->fetchCalDAVEvents($calendar, $display, $start, $end)
                    ->map(fn($e) => $e + ['source' => EventSource::CALDAV])
            );
        }

        logger()->debug('getAllEvents: raw external events fetched', [
            'display_id' => $display->id,
            'raw_count' => $rawExternal->count(),
            'all_day_filtered' => $rawExternal->filter(fn($e) => $e['isAllDay'])->count(),
        ]);

        // Load persisted DB events: custom events and tablet bookings only
        $dbEvents = Event::query()
            ->where('display_id', $display->id)
            ->where(function ($q) {
                $q->where('source', EventSource::CUSTOM)
                  ->orWhereNotNull('calendar_id');
            })
            ->where('start', '>=', $start)
            ->where('start', '<', $end)
            ->where('status', '!=', EventStatus::CANCELLED)
            ->orderBy('start')
            ->get();

        // External IDs already tracked as tablet bookings — exclude from raw external list
        $tabletExternalIds = $dbEvents->whereNotNull('external_id')->pluck('external_id')->flip()->toArray();

        $checkInEnabled = $display->isCheckInEnabled();
        $gracePeriod = $checkInEnabled ? $display->getCheckInGracePeriod() : 0;

        // Build transient Event models from external API data (not saved to DB)
        $externalModels = $rawExternal
            ->filter(fn($e) => !$e['isAllDay'])
            ->filter(fn($e) => !isset($tabletExternalIds[$e['id']]))
            ->filter(fn($e) => !$this->isEventReleased($display->id, $e['id']))
            ->map(function ($ext) use ($display, $checkInEnabled, $gracePeriod) {
                $eventStart = Carbon::parse($ext['start'])->utc();
                $eventEnd = Carbon::parse($ext['end'])->utc();
                $checkedInAt = $this->getCheckInState($display->id, $ext['id']);

                // Mark as released if check-in grace period expired without check-in
                if ($checkInEnabled && !$checkedInAt && $eventStart->lt(now()->subMinutes($gracePeriod))) {
                    $this->markEventReleased($display->id, $ext['id'], $eventEnd);
                    return null;
                }

                $event = new Event();
                $event->id = $ext['id']; // Use external calendar ID as the event identifier
                $event->display_id = $display->id;
                $event->user_id = $display->user_id;
                $event->source = $ext['source'];
                $event->external_id = $ext['id'];
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

        return $externalModels->concat($dbEvents)->sortBy('start')->values();
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

                    Cache::forget($display->getEventsCacheKey());

                    if ($calendar->google_account_id) {
                        $this->waitForEventInApi($calendar, $event->external_id, $event->start, $event->end, false);
                    }

                    $event->update(['status' => EventStatus::CANCELLED]);
                    return;
                } catch (\Exception $e) {
                    logger()->warning('Failed to delete event via API, marking as cancelled', [
                        'error' => $e->getMessage(),
                        'event_id' => $event->id,
                    ]);
                }
            }
        }

        if ($event->isCustomEvent()) {
            $event->delete();
            Cache::forget($display->getEventsCacheKey());
            return;
        }

        $event->update(['status' => EventStatus::CANCELLED]);
        Cache::forget($display->getEventsCacheKey());
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
                    Cache::forget($display->getEventsCacheKey());
                    return;
                } catch (\Exception $e) {
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
        Cache::forget($display->getEventsCacheKey());
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
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
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ]);

        // Fetch events by user (room)
        if ($calendar->room) {
            $events = $this->outlookService->fetchEventsByUser(
                outlookAccount: $calendar->outlookAccount,
                emailAddress: $calendar->calendar_id,
                startDateTime: $start,
                endDateTime: $end,
            );
        }

        // Fetch events by calendar
        if (! $calendar->room) {
            $events = $this->outlookService->fetchEventsByCalendar(
                outlookAccount: $calendar->outlookAccount,
                calendarId: $calendar->calendar_id,
                startDateTime: $start,
                endDateTime: $end,
            );
        }

        logger()->debug('fetchOutlookEvents: raw events from API', [
            'display_id' => $display->id,
            'count' => count($events),
            'first_event_summary' => count($events) > 0 ? ($events[0]['subject'] ?? 'no subject') : null,
        ]);

        return collect($events)->map(fn($e) => $this->sanitizeOutlookEvent($e));
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
     * @throws \Exception
     */
    private function fetchGoogleEvents(Calendar $calendar, Display $display, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $events = $this->googleService->fetchEvents(
            googleAccount: $calendar->googleAccount,
            calendarId: $calendar->calendar_id,
            startDateTime: $start ?? $display->getStartTime(),
            endDateTime: $end ?? $display->getEndTime(),
        );

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
            ->map(fn($e) => $this->sanitizeGoogleEvent($e));
    }

    /**
     * @param Calendar $calendar
     * @param Display $display
     * @return Collection
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

        return collect($events)->map(fn($e) => $this->sanitizeCalDAVEvent($e));
    }

    private function extractMeetingUrl(?string $text): ?string
    {
        if (!$text) {
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
            if (preg_match('#' . $pattern . '#i', $text, $matches)) {
                return rtrim($matches[0], '.,;)');
            }
        }

        return null;
    }

    private function cleanSubject(?string $subject): string
    {
        $subject ??= "";
        return trim($subject);
    }

    private function cleanBody(?string $body): string
    {
        $body ??= "";
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
            return substr($description, 0, $maxLength - 3) . '...';
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
     * Wait for an event to appear or disappear in Google Calendar API.
     * Retries with exponential backoff to handle Google's eventual consistency.
     */
    private function waitForEventInApi(Calendar $calendar, string $externalEventId, Carbon $start, Carbon $end, bool $shouldExist): void
    {
        if (!$calendar->google_account_id || !$calendar->googleAccount) {
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
                usleep((int)($delay * 1000000));

            } catch (\Exception $e) {
                logger()->warning('Error checking event in Google API during wait', [
                    'error' => $e->getMessage(),
                    'external_event_id' => $externalEventId,
                    'attempt' => $attempt,
                ]);

                if ($attempt === $maxAttempts) {
                    return;
                }

                $delay = $baseDelay * pow(2, $attempt - 1);
                usleep((int)($delay * 1000000));
            }
        }
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
