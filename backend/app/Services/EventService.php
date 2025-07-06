<?php

namespace App\Services;

use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EventService
{
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
            'timezone' => 'UTC', // CalDAV events are typically in UTC
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
     * Check if there is an active custom booking for a display and time window.
     */
    public static function getActiveCustomEvents($displayId, $now = null): Collection
    {
        $now = $now ?: now();
        return Event::query()
            ->where('display_id', $displayId)
            ->where('start', '<=', $now)
            ->where('end', '>=', $now)
            ->orderBy('start')
            ->get();
    }

    /**
     * Book a room for a given duration. Handles all business logic.
     * Throws exception if not allowed.
     */
    public function bookRoom(Display $display, User $user, int $duration, ?string $summary = null): Event
    {
        $start = now();
        $end = $start->copy()->addMinutes($duration);

        // Remove any overlapping custom events for this display
        Event::query()
            ->where('display_id', $display->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start', [$start, $end])
                  ->orWhereBetween('end', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start', '<=', $start)->where('end', '>=', $end);
                  });
            })
            ->delete();

        return Event::create([
            'display_id' => $display->id,
            'user_id' => $user->id,
            'start' => $start,
            'end' => $end,
            'summary' => $summary ?? __('Booked'),
        ]);
    }

    /**
     * Cancel an event. Only allows cancellation of custom bookings.
     */
    public function cancelEvent(string $eventId, Display $display): void
    {
        $event = Event::where('id', $eventId)
            ->where('display_id', $display->id)
            ->first();

        if (!$event) {
            throw new \Exception('Event not found or not accessible');
        }

        // All events in our database are custom bookings
        $event->delete();
    }
}
