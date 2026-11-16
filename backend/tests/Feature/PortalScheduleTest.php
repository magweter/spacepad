<?php

use App\Enums\DisplayStatus;
use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Enums\WorkspaceRole;
use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\EventService;
use App\Support\PortalSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false, 'services.events.cache_enabled' => true]);
});

/** A Pro workspace's room with booking, booking ahead, check-in and extending switched on. */
function scheduleRoom(?User $user = null, bool $pro = true): array
{
    $user ??= User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $workspace->update(['is_unlimited' => $pro]);
    $display = Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'status' => DisplayStatus::ACTIVE]);
    DisplaySettings::setBookingEnabled($display, true);
    DisplaySettings::setFutureBookingEnabled($display, true);
    DisplaySettings::setCheckInEnabled($display, true);
    DisplaySettings::setExtendEnabled($display, true);

    return [$user, $display->fresh()];
}

test('another day is fetched from the calendars for the local day asked for', function () {
    [$user, $display] = scheduleRoom();
    $this->mock(EventService::class)->shouldReceive('getEventsForDisplay')->once()
        ->withArgs(fn ($id, $date, $day) => $id === $display->id && $date->toDateString() === '2026-11-20')
        ->andReturn(collect([new Event(['summary' => 'Planning', 'start' => '2026-11-20 09:00:00', 'end' => '2026-11-20 10:00:00', 'source' => 'outlook'])]));

    $this->actingAs($user)->getJson(route('schedule.day', ['date' => '2026-11-20', 'offset' => 60]))
        ->assertOk()
        ->assertJsonPath('date', '2026-11-20')
        ->assertJsonPath('rooms.0.id', $display->id)
        ->assertJsonPath('rooms.0.events.0.title', 'Planning')
        ->assertJsonPath('rooms.0.error', null);
});

test('a calendar that fails shows as not loaded instead of failing the whole day', function () {
    [$user] = scheduleRoom();
    $this->mock(EventService::class)->shouldReceive('getEventsForDisplay')->andThrow(new RuntimeException('Graph timed out'));

    $this->actingAs($user)->getJson(route('schedule.day', ['date' => '2026-11-20', 'offset' => 0]))
        ->assertOk()
        ->assertJsonPath('rooms.0.events', [])
        ->assertJsonPath('rooms.0.error', 'This calendar could not be read.');
});

test('a paused room is not asked for another day', function () {
    [$user, $display] = scheduleRoom();
    $display->update(['status' => DisplayStatus::REQUIRES_PRO]);
    $this->mock(EventService::class)->shouldNotReceive('getEventsForDisplay');

    $this->actingAs($user)->getJson(route('schedule.day', ['date' => '2026-11-20', 'offset' => 0]))
        ->assertOk()->assertJsonPath('rooms.0.events', null);
});

test('any member of the workspace can book a room from the portal', function () {
    [$user, $display] = scheduleRoom();
    $start = now()->addHour()->startOfHour();
    $this->mock(EventService::class)->shouldReceive('bookRoom')->once()
        ->withArgs(fn ($displayId, $userId, $summary, $duration, $from, $to) => $displayId === $display->id && $userId === $user->id
            && $summary === 'Interview' && $duration === null && $from->equalTo($start) && $to->equalTo($start->copy()->addMinutes(30)))
        ->andReturn(new Event);

    $this->actingAs($user)->postJson(route('schedule.book', $display), [
        'start' => $start->toIso8601String(), 'end' => $start->copy()->addMinutes(30)->toIso8601String(), 'summary' => 'Interview',
    ])->assertOk()->assertJsonPath('message', 'Booked.');
});

test('someone outside the workspace cannot touch its rooms', function () {
    [, $display] = scheduleRoom();
    $this->mock(EventService::class)->shouldNotReceive('bookRoom', 'cancelEvent');

    $this->actingAs(User::factory()->active()->create())
        ->postJson(route('schedule.book', $display), ['start' => now()->addHour()->toIso8601String(), 'end' => now()->addHours(2)->toIso8601String()])
        ->assertNotFound();
    $this->actingAs(User::factory()->active()->create())
        ->deleteJson(route('schedule.cancel', [$display, 'abc']))
        ->assertNotFound();
});

test('the portal follows the room rules the tablet follows', function () {
    $this->mock(EventService::class)->shouldNotReceive('bookRoom', 'extendEvent', 'cancelEvent', 'checkInToEvent');
    $book = fn ($display) => ['start' => now()->addMinute()->toIso8601String(), 'end' => now()->addMinutes(31)->toIso8601String()];

    // No Pro: nothing.
    [$user, $display] = scheduleRoom(pro: false);
    $this->actingAs($user)->postJson(route('schedule.book', $display), $book($display))->assertForbidden();

    // Booking switched off.
    [$user, $display] = scheduleRoom();
    DisplaySettings::setBookingEnabled($display, false);
    $this->actingAs($user)->postJson(route('schedule.book', $display), $book($display))->assertForbidden();

    // Extending and cancelling switched off.
    DisplaySettings::setExtendEnabled($display, false);
    DisplaySettings::setCancelPermission($display, 'none');
    $this->actingAs($user)->postJson(route('schedule.extend', [$display, 'abc']), ['new_end' => now()->addHour()->toIso8601String()])->assertForbidden();
    $this->actingAs($user)->deleteJson(route('schedule.cancel', [$display, 'abc']))->assertForbidden();

    // A paused room.
    [$user, $display] = scheduleRoom();
    $display->update(['status' => DisplayStatus::REQUIRES_PRO]);
    $this->actingAs($user)->postJson(route('schedule.check-in', [$display, 'abc']))->assertForbidden();
});

test('without booking ahead a room only takes a booking that starts now', function () {
    [$user, $display] = scheduleRoom();
    DisplaySettings::setFutureBookingEnabled($display, false);
    $mock = $this->mock(EventService::class);
    $mock->shouldReceive('bookRoom')->once()->andReturn(new Event);

    $this->actingAs($user)->postJson(route('schedule.book', $display), [
        'start' => now()->addHours(3)->toIso8601String(), 'end' => now()->addHours(4)->toIso8601String(),
    ])->assertStatus(422)->assertJsonPath('message', 'This room only takes bookings that start now. Booking ahead is switched off in its settings.');

    $this->actingAs($user)->postJson(route('schedule.book', $display), [
        'start' => now()->toIso8601String(), 'end' => now()->addMinutes(30)->toIso8601String(),
    ])->assertOk();
});

test('extend, check in and cancel go through the same calls as the tablet', function () {
    [$user, $display] = scheduleRoom();
    $newEnd = now()->addHour()->startOfMinute();
    $mock = $this->mock(EventService::class);
    $mock->shouldReceive('extendEvent')->once()->withArgs(fn ($id, $displayId, $end) => $id === 'evt-1' && $displayId === $display->id && $end->equalTo($newEnd));
    $mock->shouldReceive('checkInToEvent')->once()->with('evt-1', $display->id);
    $mock->shouldReceive('cancelEvent')->once()->with('evt-1', $display->id);

    $this->actingAs($user)->postJson(route('schedule.extend', [$display, 'evt-1']), ['new_end' => $newEnd->toIso8601String()])->assertOk();
    $this->actingAs($user)->postJson(route('schedule.check-in', [$display, 'evt-1']))->assertOk();
    $this->actingAs($user)->deleteJson(route('schedule.cancel', [$display, 'evt-1']))->assertOk()->assertJsonPath('message', 'Meeting cancelled.');
});

test('a member only extends or cancels a meeting they booked themselves', function () {
    [$owner, $display] = scheduleRoom();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $display->workspace_id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
    $theirs = Event::create(['display_id' => $display->id, 'user_id' => $owner->id, 'summary' => 'Board meeting', 'start' => now()->subMinutes(10), 'end' => now()->addMinutes(20), 'source' => EventSource::CUSTOM, 'status' => EventStatus::CONFIRMED, 'timezone' => 'UTC']);
    $mine = Event::create(['display_id' => $display->id, 'user_id' => $member->id, 'summary' => 'Interview', 'start' => now()->addHour(), 'end' => now()->addHours(2), 'source' => EventSource::CUSTOM, 'status' => EventStatus::CONFIRMED, 'timezone' => 'UTC']);
    $mock = $this->mock(EventService::class);
    $mock->shouldReceive('cancelEvent')->once()->with((string) $mine->id, $display->id);
    $mock->shouldReceive('extendEvent')->never();

    // Someone else's meeting, or one straight from the calendar: no.
    $this->actingAs($member)->deleteJson(route('schedule.cancel', [$display, $theirs->id]))->assertForbidden();
    $this->actingAs($member)->deleteJson(route('schedule.cancel', [$display, 'AAMkAGI2external']))->assertForbidden();
    $this->actingAs($member)->postJson(route('schedule.extend', [$display, $theirs->id]), ['new_end' => now()->addHour()->toIso8601String()])->assertForbidden();

    // Their own booking: yes.
    $this->actingAs($member)->deleteJson(route('schedule.cancel', [$display, $mine->id]))->assertOk();
});

test('an owner changes anyone\'s meeting, and the overview says who may', function () {
    [$owner, $display] = scheduleRoom();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $display->workspace_id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
    $this->mock(EventService::class)->shouldReceive('cancelEvent')->once()->with('AAMkAGI2external', $display->id);

    $this->actingAs($owner)->deleteJson(route('schedule.cancel', [$display, 'AAMkAGI2external']))->assertOk();

    expect(PortalSchedule::actions($display, $owner)['manage'])->toBeTrue()
        ->and(PortalSchedule::actions($display, $member)['manage'])->toBeFalse();
});

test('the timeline cannot be used to hammer the calendars', function () {
    [$user, $display] = scheduleRoom();
    $this->mock(EventService::class)->shouldReceive('getEventsForDisplay')->andReturn(collect());

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)->getJson(route('schedule.day', ['date' => '2026-11-20', 'offset' => 0]))->assertOk();
    }
    $this->actingAs($user)->getJson(route('schedule.day', ['date' => '2026-11-20', 'offset' => 0]))->assertStatus(429);
});

test('a refusal from the calendar side comes back as a readable message', function () {
    [$user, $display] = scheduleRoom();
    DisplaySettings::setCancelPermission($display, 'tablet_only');
    $this->mock(EventService::class)->shouldReceive('cancelEvent')->andThrow(new Exception('Only events booked via this tablet can be cancelled'));

    $this->actingAs($user)->deleteJson(route('schedule.cancel', [$display, 'evt-1']))
        ->assertStatus(422)->assertJsonPath('message', 'Only events booked via this tablet can be cancelled');
});

test('the overview tells the timeline what each room allows', function () {
    [$user, $display] = scheduleRoom();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('rooms.0.actions.book', true)
            ->where('rooms.0.actions.bookAhead', true)
            ->where('rooms.0.actions.extend', true)
            ->where('rooms.0.actions.cancel', 'all')
            ->where('rooms.0.urls.book', route('schedule.book', $display))
            ->where('urls.day', route('schedule.day')));
});
