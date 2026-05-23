<?php

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\PermissionType;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Services\EventService;
use App\Services\OutlookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// Helper: build a minimal Outlook API event array accepted by sanitizeOutlookEvent
function outlookApiEvent(string $id, Carbon $start, Carbon $end, string $subject = 'Meeting'): array
{
    return [
        'id' => $id,
        'subject' => $subject,
        'body' => ['content' => ''],
        'bodyPreview' => '',
        'isAllDay' => false,
        'location' => ['displayName' => ''],
        'start' => ['dateTime' => $start->utc()->toIso8601String(), 'timeZone' => 'UTC'],
        'end' => ['dateTime' => $end->utc()->toIso8601String(), 'timeZone' => 'UTC'],
        'onlineMeeting' => null,
        'onlineMeetingUrl' => null,
        'organizer' => ['emailAddress' => ['name' => 'Organizer']],
    ];
}

beforeEach(function () {
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

// ---------------------------------------------------------------------------
// Tablet booking merge
// ---------------------------------------------------------------------------

it('merges tablet booking with external event by exact external_id and returns DB ULID as event id', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'permission_type' => PermissionType::WRITE,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $extId = 'outlook-ext-exact-match';
    $start = now()->addHour();
    $end = now()->addHours(2);

    $tabletBooking = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'external_id' => $extId,
        'source' => EventSource::OUTLOOK,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Booked via tablet',
        'start' => $start,
        'end' => $end,
        'timezone' => 'UTC',
    ]);

    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once()
        ->andReturn([outlookApiEvent($extId, $start, $end)]);
    $this->app->instance(OutlookService::class, $outlookService);

    $events = app(EventService::class)->getEventsForDisplay($this->display->id);

    // One event (merged — not duplicated)
    expect($events)->toHaveCount(1);
    // ID must be the DB ULID so that cancel/extend/check-in route to the DB row
    expect($events->first()->id)->toBe($tabletBooking->id);
    // External ID must be preserved for API operations
    expect($events->first()->external_id)->toBe($extId);
});

it('merges tablet booking with external event by start-time when external ids differ (user_account room)', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'permission_type' => PermissionType::WRITE,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $start = now()->addHour()->startOfMinute();
    $end = now()->addHours(2)->startOfMinute();

    // The tablet booking stores the user-calendar event ID
    $tabletBooking = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'external_id' => 'user-calendar-event-id',
        'source' => EventSource::OUTLOOK,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Booked via tablet',
        'start' => $start,
        'end' => $end,
        'timezone' => 'UTC',
    ]);

    // The room calendar returns a different event ID for the same meeting
    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once()
        ->andReturn([outlookApiEvent('room-calendar-event-id', $start, $end)]);
    $this->app->instance(OutlookService::class, $outlookService);

    $events = app(EventService::class)->getEventsForDisplay($this->display->id);

    expect($events)->toHaveCount(1);
    expect($events->first()->id)->toBe($tabletBooking->id);
});

it('shows unmatched tablet booking from DB when API returns no events', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'permission_type' => PermissionType::WRITE,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $tabletBooking = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'external_id' => 'some-ext-id',
        'source' => EventSource::OUTLOOK,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Booked via tablet',
        'start' => now()->addHour(),
        'end' => now()->addHours(2),
        'timezone' => 'UTC',
    ]);

    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')->once()->andReturn([]);
    $this->app->instance(OutlookService::class, $outlookService);

    $events = app(EventService::class)->getEventsForDisplay($this->display->id);

    expect($events)->toHaveCount(1);
    expect($events->first()->id)->toBe($tabletBooking->id);
});

it('does not duplicate when external event has no matching tablet booking', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $start = now()->addHour();
    $end = now()->addHours(2);

    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('fetchEventsByUser')
        ->once()
        ->andReturn([outlookApiEvent('external-only-id', $start, $end)]);
    $this->app->instance(OutlookService::class, $outlookService);

    $events = app(EventService::class)->getEventsForDisplay($this->display->id);

    expect($events)->toHaveCount(1);
    // Pure external event: ID equals the external calendar event ID
    expect($events->first()->id)->toBe('external-only-id');
});

// ---------------------------------------------------------------------------
// Custom events
// ---------------------------------------------------------------------------

it('includes custom events without any external calendar', function () {
    $customEvent = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'source' => EventSource::CUSTOM,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Walk-in booking',
        'start' => now()->addHour(),
        'end' => now()->addHours(2),
        'timezone' => 'UTC',
    ]);

    $events = app(EventService::class)->getEventsForDisplay($this->display->id);

    expect($events)->toHaveCount(1);
    expect($events->first()->id)->toBe($customEvent->id);
    expect($events->first()->summary)->toBe('Walk-in booking');
});

// ---------------------------------------------------------------------------
// Cancel routing
// ---------------------------------------------------------------------------

it('cancels tablet booking via DB row and calls API delete', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'permission_type' => PermissionType::WRITE,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $tabletBooking = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'calendar_id' => $this->calendar->id,
        'external_id' => 'ext-to-delete',
        'source' => EventSource::OUTLOOK,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Tablet booking',
        'start' => now()->addHour(),
        'end' => now()->addHours(2),
        'timezone' => 'UTC',
    ]);

    $outlookService = Mockery::mock(OutlookService::class);
    $outlookService->shouldReceive('deleteEvent')
        ->once()
        ->withArgs(fn ($account, $calendar, $id) => $id === 'ext-to-delete');
    $this->app->instance(OutlookService::class, $outlookService);

    app(EventService::class)->cancelEvent($tabletBooking->id, $this->display->id);

    expect($tabletBooking->fresh()->status)->toBe(EventStatus::CANCELLED->value);
});

it('marks pure external event as released in redis when cancelled without write permission', function () {
    $outlookAccount = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'permission_type' => PermissionType::READ,
    ]);
    $this->calendar->update(['outlook_account_id' => $outlookAccount->id]);

    $externalId = 'read-only-external-id';

    app(EventService::class)->cancelEvent($externalId, $this->display->id);

    expect(cache()->has("released:{$this->display->id}:{$externalId}"))->toBeTrue();
});

it('deletes custom event from db on cancel', function () {
    $customEvent = Event::create([
        'display_id' => $this->display->id,
        'user_id' => $this->user->id,
        'source' => EventSource::CUSTOM,
        'status' => EventStatus::CONFIRMED,
        'summary' => 'Walk-in',
        'start' => now()->addHour(),
        'end' => now()->addHours(2),
        'timezone' => 'UTC',
    ]);

    app(EventService::class)->cancelEvent($customEvent->id, $this->display->id);

    expect(Event::find($customEvent->id))->toBeNull();
});
