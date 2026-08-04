<?php

use App\Enums\EventSource;
use App\Enums\EventStatus;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

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

    // Fixed point in time so the day boundaries are unambiguous. The server runs in UTC.
    Carbon::setTestNow(Carbon::parse('2026-08-03T17:30:00Z'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A local (custom) event, which needs no calendar provider to be mocked. */
function dayEvent(Display $display, User $user, string $start, string $end, string $summary): Event
{
    return Event::create([
        'display_id' => $display->id,
        'user_id' => $user->id,
        'calendar_id' => null,
        'external_id' => null,
        'source' => EventSource::CUSTOM,
        'status' => EventStatus::CONFIRMED,
        'summary' => $summary,
        'start' => Carbon::parse($start),
        'end' => Carbon::parse($end),
        'timezone' => 'UTC',
    ]);
}

test('a tablet that states its day only gets that day', function () {
    dayEvent($this->display, $this->user, '2026-08-03T19:00:00Z', '2026-08-03T20:00:00Z', 'Today late');
    // 08:00 in Amsterdam the next morning — the booking that used to surface as "Next" at 19:36.
    dayEvent($this->display, $this->user, '2026-08-04T06:00:00Z', '2026-08-04T07:00:00Z', 'Tomorrow morning');

    $response = $this->actingAs($this->device)
        ->withHeaders(['X-Local-Date' => '2026-08-03', 'X-Utc-Offset' => '120'])
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    $summaries = collect($response->json('data.events'))->pluck('summary');

    expect($summaries)->toContain('Today late')
        ->and($summaries)->not->toContain('Tomorrow morning');
});

test('a meeting running across local midnight is kept', function () {
    // 23:30–00:30 Amsterdam time.
    dayEvent($this->display, $this->user, '2026-08-03T21:30:00Z', '2026-08-03T22:30:00Z', 'Late night');

    $response = $this->actingAs($this->device)
        ->withHeaders(['X-Local-Date' => '2026-08-03', 'X-Utc-Offset' => '120'])
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    expect(collect($response->json('data.events'))->pluck('summary'))->toContain('Late night');
});

test('a far-offset tablet gets its own evening, not the servers day', function () {
    // Auckland is UTC+12: its 4 August starts at 3 August 12:00 UTC. An 8pm local meeting on the
    // 4th is 08:00 UTC on the 4th; the server's own 3 August window would have missed it entirely.
    dayEvent($this->display, $this->user, '2026-08-04T08:00:00Z', '2026-08-04T09:00:00Z', 'Auckland evening');

    $response = $this->actingAs($this->device)
        ->withHeaders(['X-Local-Date' => '2026-08-04', 'X-Utc-Offset' => '720'])
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    expect(collect($response->json('data.events'))->pluck('summary'))->toContain('Auckland evening');
});

test('an app build without the headers falls back to the server day', function () {
    dayEvent($this->display, $this->user, '2026-08-03T19:00:00Z', '2026-08-03T20:00:00Z', 'Today late');
    dayEvent($this->display, $this->user, '2026-08-04T06:00:00Z', '2026-08-04T07:00:00Z', 'Tomorrow morning');

    $response = $this->actingAs($this->device)
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    $summaries = collect($response->json('data.events'))->pluck('summary');

    // Still no leaking of tomorrow, which is the regression this fixes for old builds too.
    expect($summaries)->toContain('Today late')
        ->and($summaries)->not->toContain('Tomorrow morning');
});

test('the schedule endpoint returns the requested day in the tablets timezone', function () {
    dayEvent($this->display, $this->user, '2026-08-05T06:00:00Z', '2026-08-05T07:00:00Z', 'Wednesday morning');
    dayEvent($this->display, $this->user, '2026-08-04T06:00:00Z', '2026-08-04T07:00:00Z', 'Tuesday morning');

    $response = $this->actingAs($this->device)
        ->withHeaders(['X-Local-Date' => '2026-08-03', 'X-Utc-Offset' => '120'])
        ->getJson("/api/displays/{$this->display->id}/events?date=2026-08-05")
        ->assertOk();

    $summaries = collect($response->json('data'))->pluck('summary');

    expect($summaries)->toContain('Wednesday morning')
        ->and($summaries)->not->toContain('Tuesday morning');
});

test('the schedule endpoint keeps its wider range for app builds without the headers', function () {
    // The old contract: hand back a day either side and let the client clamp. Narrowing it here
    // would strip part of the local day for tablets in far-offset timezones.
    dayEvent($this->display, $this->user, '2026-08-04T06:00:00Z', '2026-08-04T07:00:00Z', 'Tuesday morning');
    dayEvent($this->display, $this->user, '2026-08-05T06:00:00Z', '2026-08-05T07:00:00Z', 'Wednesday morning');

    $response = $this->actingAs($this->device)
        ->getJson("/api/displays/{$this->display->id}/events?date=2026-08-04")
        ->assertOk();

    $summaries = collect($response->json('data'))->pluck('summary');

    expect($summaries)->toContain('Tuesday morning')
        ->and($summaries)->toContain('Wednesday morning');
});

test('the fetch log records which day was used and where it came from', function () {
    Log::spy();

    $this->actingAs($this->device)
        ->withHeaders(['X-Local-Date' => '2026-08-03', 'X-Utc-Offset' => '330'])
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return $message === 'Display data fetched'
                && $context['local_date_header'] === '2026-08-03'
                && $context['utc_offset_header'] === '330'
                && $context['day_source'] === 'tablet'
                // India is UTC+5:30, so its 3 August starts at 2 August 18:30 UTC.
                && $context['day_start'] === '2026-08-02T18:30:00+00:00'
                && $context['day_end'] === '2026-08-03T18:29:59+00:00';
        })
        ->once();
});

test('the fetch log marks a request without headers as using the server day', function () {
    Log::spy();

    $this->actingAs($this->device)
        ->getJson("/api/displays/{$this->display->id}/data")
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return $message === 'Display data fetched'
                && $context['day_source'] === 'server'
                && $context['local_date_header'] === null
                && $context['utc_offset_header'] === null
                && $context['day_start'] === '2026-08-03T00:00:00+00:00';
        })
        ->once();
});
