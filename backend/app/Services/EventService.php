<?php

namespace App\Services;

use Google\Service\Calendar\Event;
use Illuminate\Support\Arr;

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
     * @param Event $googleEvent
     * @return array
     */
    public function sanitizeGoogleEvent(Event $googleEvent): array
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

    private function cleanSubject(string $subject): string
    {
        return trim($subject); // Basic cleanup, can be expanded if necessary
    }

    private function cleanBody(string $body): string
    {
        // Replace newlines and carriage returns as in JS version
        $body = str_replace("\r", "\n", $body);
        return str_replace("\n", ' ', $body);
    }
}
