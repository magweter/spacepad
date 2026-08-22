<?php

use App\Services\EventService;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventCreator;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventOrganizer;

/**
 * Whose name ends up on the wall.
 *
 * Google only fills organizer.displayName for named calendars, so for a person booking a room
 * it is usually empty and the raw address was displayed instead. The name is normally still in
 * the event, just on the creator or on the organiser's own attendee entry.
 */
function googleEventWith(array $parts): GoogleEvent
{
    $event = new GoogleEvent;
    $event->setId('evt-1');
    $event->setSummary('Testmeeting!');

    $start = new EventDateTime;
    $start->setDateTime('2026-08-16T15:00:00+02:00');
    $end = new EventDateTime;
    $end->setDateTime('2026-08-16T18:00:00+02:00');
    $event->setStart($start);
    $event->setEnd($end);

    if (isset($parts['organizer'])) {
        $organizer = new EventOrganizer;
        $organizer->setEmail($parts['organizer']['email'] ?? null);
        $organizer->setDisplayName($parts['organizer']['displayName'] ?? null);
        $event->setOrganizer($organizer);
    }

    if (isset($parts['creator'])) {
        $creator = new EventCreator;
        $creator->setEmail($parts['creator']['email'] ?? null);
        $creator->setDisplayName($parts['creator']['displayName'] ?? null);
        $event->setCreator($creator);
    }

    if (isset($parts['attendees'])) {
        $event->setAttendees(array_map(function (array $data) {
            $attendee = new EventAttendee;
            $attendee->setEmail($data['email'] ?? null);
            $attendee->setDisplayName($data['displayName'] ?? null);
            $attendee->setOrganizer($data['organizer'] ?? false);

            return $attendee;
        }, $parts['attendees']));
    }

    return $event;
}

function organizerOf(array $parts): ?string
{
    return app(EventService::class)->sanitizeGoogleEvent(googleEventWith($parts))['organizer_name'];
}

test('the organizer display name wins when Google provides one', function () {
    expect(organizerOf([
        'organizer' => ['email' => 'martijn@example.com', 'displayName' => 'Martijn van de Wetering'],
    ]))->toBe('Martijn van de Wetering');
});

test('it falls back to the creator when the organizer has no display name', function () {
    expect(organizerOf([
        'organizer' => ['email' => 'admin@magweter.com'],
        'creator' => ['email' => 'admin@magweter.com', 'displayName' => 'Martijn van de Wetering'],
    ]))->toBe('Martijn van de Wetering');
});

test('it falls back to the attendee entry of the organizer', function () {
    expect(organizerOf([
        'organizer' => ['email' => 'admin@magweter.com'],
        'attendees' => [
            ['email' => 'someone@example.com', 'displayName' => 'Iemand Anders'],
            ['email' => 'admin@magweter.com', 'displayName' => 'Martijn van de Wetering', 'organizer' => true],
        ],
    ]))->toBe('Martijn van de Wetering');
});

test('with no name anywhere the address is made presentable', function () {
    expect(organizerOf([
        'organizer' => ['email' => 'admin@magweter.com'],
    ]))->toBe('Admin');

    expect(organizerOf([
        'organizer' => ['email' => 'jan.de.vries@example.com'],
    ]))->toBe('Jan de Vries');
});

test('an address that is not a name is left alone', function () {
    // Booking systems and resource calendars use addresses no amount of capitalising helps.
    expect(organizerOf([
        'organizer' => ['email' => '4f9a2b1c@resource.calendar.google.com'],
    ]))->toBe('4f9a2b1c@resource.calendar.google.com');
});

test('a display name that is really an address is tidied up too', function () {
    // Google and Microsoft both hand back the address as the "name" for external organisers.
    expect(organizerOf([
        'organizer' => ['email' => 'jan.de.vries@example.com', 'displayName' => 'jan.de.vries@example.com'],
    ]))->toBe('Jan de Vries');
});

test('an Outlook organizer keeps its real name', function () {
    $sanitized = app(EventService::class)->sanitizeOutlookEvent([
        'id' => 'evt-2',
        'subject' => 'Testmeeting!',
        'body' => ['content' => ''],
        'bodyPreview' => '',
        'isAllDay' => false,
        'location' => ['displayName' => ''],
        'start' => ['dateTime' => '2026-08-16T13:00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-08-16T16:00:00', 'timeZone' => 'UTC'],
        'onlineMeeting' => null,
        'onlineMeetingUrl' => null,
        'organizer' => ['emailAddress' => [
            'name' => 'Martijn van de Wetering',
            'address' => 'martijn@example.com',
        ]],
    ]);

    expect($sanitized['organizer_name'])->toBe('Martijn van de Wetering');
});

test('an Outlook organizer without a real name falls back to the address', function () {
    $sanitized = app(EventService::class)->sanitizeOutlookEvent([
        'id' => 'evt-3',
        'subject' => 'Testmeeting!',
        'body' => ['content' => ''],
        'bodyPreview' => '',
        'isAllDay' => false,
        'location' => ['displayName' => ''],
        'start' => ['dateTime' => '2026-08-16T13:00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-08-16T16:00:00', 'timeZone' => 'UTC'],
        'onlineMeeting' => null,
        'onlineMeetingUrl' => null,
        'organizer' => ['emailAddress' => [
            'name' => 'jan.de.vries@example.com',
            'address' => 'jan.de.vries@example.com',
        ]],
    ]);

    expect($sanitized['organizer_name'])->toBe('Jan de Vries');
});
