<?php

use App\Enums\PermissionType;
use App\Helpers\DisplaySettings;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Services\EventService;
use App\Services\OutlookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function roomApiEvent(string $id, Carbon $start, Carbon $end): array
{
    return [
        'id' => $id,
        'subject' => 'Meeting',
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

/**
 * A tablet acts on events by id. For ids not in our own events table the id is handed to the
 * provider as is, so a paired tablet could delete or extend any event the linked account can
 * reach, not only what is on this room's calendar. The id has to appear in the display's
 * own event set first.
 */
beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create();
    $workspace = $this->user->primaryWorkspace();

    $this->account = OutlookAccount::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $workspace->id,
        'permission_type' => PermissionType::WRITE,
    ]);

    $calendar = Calendar::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $workspace->id,
        'calendar_id' => 'room@example.com',
        'outlook_account_id' => $this->account->id,
    ]);

    $this->display = Display::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $workspace->id,
        'calendar_id' => $calendar->id,
        'status' => 'active',
    ]);

    Room::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $workspace->id,
        'calendar_id' => $calendar->id,
        'email_address' => 'room@example.com',
    ]);

    DisplaySettings::setExtendEnabled($this->display, true);

    $this->onRoom = roomApiEvent('on-this-room', now()->addHour(), now()->addHours(2));
});

it('refuses to cancel an external event that is not on the display', function () {
    $outlook = Mockery::mock(OutlookService::class);
    $outlook->shouldReceive('fetchEventsByUser')->andReturn([$this->onRoom]);
    $outlook->shouldNotReceive('deleteEvent');
    $this->app->instance(OutlookService::class, $outlook);

    expect(fn () => app(EventService::class)->cancelEvent('somebody-elses-event', $this->display->id))
        ->toThrow(Exception::class, 'Event not found on this display');
});

it('cancels an external event that is on the display', function () {
    $outlook = Mockery::mock(OutlookService::class);
    $outlook->shouldReceive('fetchEventsByUser')->andReturn([$this->onRoom]);
    $outlook->shouldReceive('deleteEvent')->once()
        ->withArgs(fn ($account, $calendar, $id) => $id === 'on-this-room');
    $this->app->instance(OutlookService::class, $outlook);

    app(EventService::class)->cancelEvent('on-this-room', $this->display->id);
});

it('refuses to extend an external event that is not on the display', function () {
    $outlook = Mockery::mock(OutlookService::class);
    $outlook->shouldReceive('fetchEventsByUser')->andReturn([$this->onRoom]);
    $outlook->shouldNotReceive('patchEventEndTime');
    $this->app->instance(OutlookService::class, $outlook);

    expect(fn () => app(EventService::class)->extendEvent('somebody-elses-event', $this->display->id, now()->addHours(3)))
        ->toThrow(Exception::class, 'Event not found on this display');
});

it('extends an external event that is on the display', function () {
    $outlook = Mockery::mock(OutlookService::class);
    $outlook->shouldReceive('fetchEventsByUser')->andReturn([$this->onRoom]);
    $outlook->shouldReceive('patchEventEndTime')->once()
        ->withArgs(fn ($account, $calendar, $id, $end) => $id === 'on-this-room');
    $this->app->instance(OutlookService::class, $outlook);

    app(EventService::class)->extendEvent('on-this-room', $this->display->id, now()->addHours(3));
});
