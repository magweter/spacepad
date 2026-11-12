<?php

use App\Enums\PermissionType;
use App\Helpers\DisplaySettings;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Services\CalDAVService;
use App\Services\EventService;
use App\Services\GoogleService;
use App\Services\OutlookService;
use App\Support\LocalDay;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** An Outlook calendarview item as Graph returns it with Prefer: outlook.timezone="UTC". */
function outlookItem(string $id, string $start, string $end, bool $allDay = false, string $showAs = 'busy', string $subject = 'Meeting'): array
{
    return [
        'id' => $id,
        'subject' => $subject,
        'body' => ['content' => ''],
        'bodyPreview' => '',
        'isAllDay' => $allDay,
        'showAs' => $showAs,
        'location' => ['displayName' => ''],
        'start' => ['dateTime' => $start, 'timeZone' => 'UTC'],
        'end' => ['dateTime' => $end, 'timeZone' => 'UTC'],
        'onlineMeeting' => null,
        'onlineMeetingUrl' => null,
        'organizer' => ['emailAddress' => ['name' => 'Organizer']],
    ];
}

/** A calendar-query multi-status body holding one VCALENDAR per given VEVENT. */
function caldavBody(string ...$vevents): string
{
    $responses = collect($vevents)->map(fn (string $vevent) => <<<XML
<d:response><d:propstat><d:prop><cal:calendar-data>BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
{$vevent}
END:VCALENDAR
</cal:calendar-data></d:prop></d:propstat></d:response>
XML)->implode("\n");

    return '<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">'.$responses.'</d:multistatus>';
}

beforeEach(function () {
    // 10:00 UTC on 10 November, 11:00 in Amsterdam. The server runs in UTC.
    Carbon::setTestNow(Carbon::parse('2026-11-10T10:00:00Z'));

    $this->user = User::factory()->create();
    $this->workspace = $this->user->primaryWorkspace();

    $this->device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->calendar = Calendar::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'calendar_id' => 'room@example.com',
        'name' => 'Test Room Calendar',
    ]);

    $this->display = Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'calendar_id' => $this->calendar->id,
        'status' => 'active',
    ]);

    $this->device->update(['display_id' => $this->display->id]);

    Room::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'calendar_id' => $this->calendar->id,
        'email_address' => 'room@example.com',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function useOutlook(object $test, array $items, PermissionType $permission = PermissionType::READ): void
{
    $account = OutlookAccount::factory()->create([
        'user_id' => $test->user->id,
        'permission_type' => $permission,
    ]);
    $test->calendar->update(['outlook_account_id' => $account->id]);

    $outlook = Mockery::mock(OutlookService::class);
    $outlook->shouldReceive('fetchEventsByUser')->andReturn($items);
    app()->instance(OutlookService::class, $outlook);
}

function amsterdamEvents(object $test): array
{
    return $test->actingAs($test->device)
        ->withHeaders(['X-Local-Date' => '2026-11-10', 'X-Utc-Offset' => '60'])
        ->getJson("/api/displays/{$test->display->id}/data")
        ->assertOk()
        ->json('data.events');
}

// ---------------------------------------------------------------------------
// The room is busy
// ---------------------------------------------------------------------------

test('an all-day event blocks the room from the tablets own midnight to the next', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true, subject: 'Training day'),
    ]);

    $events = amsterdamEvents($this);

    expect($events)->toHaveCount(1)
        ->and($events[0]['summary'])->toBe('Training day')
        ->and($events[0]['isAllDay'])->toBeTrue()
        // Midnight in Amsterdam, not midnight on the server.
        ->and(Carbon::parse($events[0]['start'])->equalTo(Carbon::parse('2026-11-10T00:00:00+01:00')))->toBeTrue()
        ->and(Carbon::parse($events[0]['end'])->equalTo(Carbon::parse('2026-11-11T00:00:00+01:00')))->toBeTrue()
        ->and($events[0]['checkInRequired'])->toBeFalse();
});

test('an all-day event on another day does not show', function () {
    useOutlook($this, [
        outlookItem('yesterday', '2026-11-09T00:00:00.0000000', '2026-11-10T00:00:00.0000000', allDay: true),
        outlookItem('tomorrow', '2026-11-11T00:00:00.0000000', '2026-11-12T00:00:00.0000000', allDay: true),
    ]);

    expect(amsterdamEvents($this))->toBeEmpty();
});

test('a multi-day event blocks every day it covers', function () {
    useOutlook($this, [
        outlookItem('conference', '2026-11-09T00:00:00.0000000', '2026-11-12T00:00:00.0000000', allDay: true, subject: 'Conference'),
    ]);

    $events = amsterdamEvents($this);

    expect($events)->toHaveCount(1)
        ->and($events[0]['summary'])->toBe('Conference');
});

test('the cached all-day event keeps its server placement for the next tablet', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true),
    ]);

    $service = app(EventService::class);
    $amsterdam = $service->getEventsForDisplay($this->display->id, null, LocalDay::fromDateAndOffset('2026-11-10', 60));
    $newYork = $service->getEventsForDisplay($this->display->id, null, LocalDay::fromDateAndOffset('2026-11-10', -300));

    expect($amsterdam->first()->start->equalTo(Carbon::parse('2026-11-09T23:00:00Z')))->toBeTrue()
        ->and($newYork->first()->start->equalTo(Carbon::parse('2026-11-10T05:00:00Z')))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Free events never block
// ---------------------------------------------------------------------------

test('events marked free do not block the room, all-day or not', function () {
    useOutlook($this, [
        outlookItem('free-all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true, showAs: 'free'),
        outlookItem('free-timed', '2026-11-10T10:00:00.0000000', '2026-11-10T11:00:00.0000000', showAs: 'free'),
        outlookItem('tentative', '2026-11-10T12:00:00.0000000', '2026-11-10T13:00:00.0000000', showAs: 'tentative', subject: 'Maybe'),
    ]);

    $events = amsterdamEvents($this);

    expect(collect($events)->pluck('summary')->all())->toBe(['Maybe']);
});

test('google all-day events block the room and transparent events do not', function () {
    $account = GoogleAccount::factory()->create(['user_id' => $this->user->id]);
    $this->calendar->update(['google_account_id' => $account->id]);

    $allDay = new GoogleEvent([
        'id' => 'g-all-day',
        'summary' => 'Offsite',
        'status' => 'confirmed',
    ]);
    $allDay->setStart(new EventDateTime(['date' => '2026-11-10']));
    $allDay->setEnd(new EventDateTime(['date' => '2026-11-11']));

    $available = new GoogleEvent([
        'id' => 'g-free',
        'summary' => 'Reminder',
        'status' => 'confirmed',
        'transparency' => 'transparent',
    ]);
    $available->setStart(new EventDateTime(['dateTime' => '2026-11-10T13:00:00Z']));
    $available->setEnd(new EventDateTime(['dateTime' => '2026-11-10T14:00:00Z']));

    $google = Mockery::mock(GoogleService::class);
    $google->shouldReceive('fetchEvents')->andReturn([$allDay, $available]);
    app()->instance(GoogleService::class, $google);

    $events = amsterdamEvents($this);

    expect(collect($events)->pluck('summary')->all())->toBe(['Offsite'])
        ->and($events[0]['isAllDay'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// The setting
// ---------------------------------------------------------------------------

test('all-day events block the room by default', function () {
    expect(DisplaySettings::isAllDayEventsBlocking($this->display))->toBeTrue();
});

test('a display set to ignore all-day events still shows its timed events', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true, subject: 'Note'),
        outlookItem('timed', '2026-11-10T12:00:00.0000000', '2026-11-10T13:00:00.0000000', subject: 'Standup'),
    ]);
    DisplaySettings::setAllDayEventsBlocking($this->display, false);

    expect(collect(amsterdamEvents($this))->pluck('summary')->all())->toBe(['Standup']);
});

test('the setting is saved from the display configuration screen', function () {
    $owner = User::factory()->active()->unlimited()->create();
    $workspace = $owner->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);
    $display = Display::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'status' => 'active']);

    // An unchecked box is simply absent from the form.
    $this->actingAs($owner)
        ->put(route('displays.section.update', ['display' => $display, 'section' => 'display']), [
            'timeline_widget_mode' => 'none',
            'border_thickness' => 'medium',
        ])
        ->assertRedirect(route('displays.configure', $display));

    expect(DisplaySettings::isAllDayEventsBlocking($display->fresh()))->toBeFalse();

    $this->actingAs($owner)
        ->put(route('displays.section.update', ['display' => $display, 'section' => 'display']), [
            'all_day_events_block' => '1',
        ]);

    expect(DisplaySettings::isAllDayEventsBlocking($display->fresh()))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Check-in, extend and booking
// ---------------------------------------------------------------------------

test('check-in never releases an all-day event', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true, subject: 'Training day'),
    ]);
    DisplaySettings::setCheckInEnabled($this->display, true);
    DisplaySettings::setCheckInGracePeriod($this->display, 5);

    $events = amsterdamEvents($this);

    expect(collect($events)->pluck('summary')->all())->toBe(['Training day']);
});

test('an all-day event cannot be extended', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true),
    ], PermissionType::WRITE);
    DisplaySettings::setExtendEnabled($this->display, true);

    expect(fn () => app(EventService::class)->extendEvent('all-day', $this->display->id, now()->addDay()))
        ->toThrow(Exception::class, 'All-day events cannot be extended');
});

test('booking during an all-day event conflicts unless the display ignores them', function () {
    useOutlook($this, [
        outlookItem('all-day', '2026-11-10T00:00:00.0000000', '2026-11-11T00:00:00.0000000', allDay: true),
    ]);
    $service = app(EventService::class);

    expect($service->hasConflictingEvents($this->display->id, now(), now()->addHour()))->toBeTrue();

    DisplaySettings::setAllDayEventsBlocking($this->display, false);

    expect($service->hasConflictingEvents($this->display->id, now(), now()->addHour()))->toBeFalse();
});

// ---------------------------------------------------------------------------
// CalDAV (Nextcloud)
// ---------------------------------------------------------------------------

test('caldav hands all-day events on as dates and reads TRANSP', function () {
    $events = (new CalDAVService)->parseEvents(caldavBody(
        "BEGIN:VEVENT\nUID:all-day\nSUMMARY:Closed\nDTSTART;VALUE=DATE:20261110\nDTEND;VALUE=DATE:20261111\nEND:VEVENT",
        "BEGIN:VEVENT\nUID:free\nSUMMARY:Note\nTRANSP:TRANSPARENT\nDTSTART:20261110T120000Z\nDTEND:20261110T130000Z\nEND:VEVENT",
    ));

    expect($events[0])->toMatchArray(['start' => '2026-11-10', 'end' => '2026-11-11', 'isAllDay' => true, 'isFree' => false])
        ->and($events[1])->toMatchArray(['isAllDay' => false, 'isFree' => true]);
});

test('caldav events without DTEND no longer break the sync', function () {
    $events = (new CalDAVService)->parseEvents(caldavBody(
        "BEGIN:VEVENT\nUID:no-end\nSUMMARY:Closed\nDTSTART;VALUE=DATE:20261110\nEND:VEVENT",
        "BEGIN:VEVENT\nUID:duration\nSUMMARY:Workshop\nDTSTART:20261110T120000Z\nDURATION:PT2H\nEND:VEVENT",
    ));

    expect($events[0])->toMatchArray(['start' => '2026-11-10', 'end' => '2026-11-11', 'isAllDay' => true])
        ->and($events[1]['end'])->toBe('2026-11-10T14:00:00+00:00');
});

test('a nextcloud all-day event blocks the room end to end', function () {
    $account = CalDAVAccount::factory()->create(['user_id' => $this->user->id]);
    $this->calendar->update(['caldav_account_id' => $account->id]);

    $parsed = (new CalDAVService)->parseEvents(caldavBody(
        "BEGIN:VEVENT\nUID:nc-all-day\nSUMMARY:Board meeting\nDTSTART;VALUE=DATE:20261110\nDTEND;VALUE=DATE:20261111\nEND:VEVENT",
    ));

    $caldav = Mockery::mock(CalDAVService::class);
    $caldav->shouldReceive('fetchEvents')->andReturn($parsed);
    app()->instance(CalDAVService::class, $caldav);

    $events = amsterdamEvents($this);

    expect($events)->toHaveCount(1)
        ->and($events[0]['summary'])->toBe('Board meeting')
        ->and($events[0]['isAllDay'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// LocalDay
// ---------------------------------------------------------------------------

test('a date is placed on the days own midnight', function () {
    expect(LocalDay::fromDateAndOffset('2026-11-10', 60)->midnightOf('2026-11-12')->toIso8601String())
        ->toBe('2026-11-11T23:00:00+00:00')
        ->and(LocalDay::serverDay(Carbon::parse('2026-11-10T10:00:00Z'))->midnightOf('2026-11-10')->toIso8601String())
        ->toBe('2026-11-10T00:00:00+00:00');
});

test('the configure screen and the profile form offer the setting, checked by default', function () {
    $owner = User::factory()->active()->unlimited()->create();
    $workspace = $owner->primaryWorkspace();
    session()->put('selected_workspace_id', $workspace->id);
    $display = Display::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'status' => 'active']);

    $configure = $this->actingAs($owner)->get(route('displays.configure', $display))->assertOk();
    $profile = $this->actingAs($owner)->get(route('profiles.create'))->assertOk();

    foreach ([$configure, $profile] as $response) {
        expect($response->getContent())
            ->toContain('All-day events block the room')
            ->toMatch('/name="all_day_events_block" [^>]*value="1"[^>]*checked/s');
    }
});
