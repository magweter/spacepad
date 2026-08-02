<?php

use App\Support\LocalDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Build a request carrying the headers a tablet sends. */
function requestWithLocalDay(?string $date, ?string $offset): Request
{
    $headers = [];

    if ($date !== null) {
        $headers['HTTP_X_LOCAL_DATE'] = $date;
    }

    if ($offset !== null) {
        $headers['HTTP_X_UTC_OFFSET'] = $offset;
    }

    return Request::create('/api/displays/1/data', 'GET', [], [], [], $headers);
}

test('a positive offset shifts the window back from UTC midnight', function () {
    // Auckland is UTC+13 in summer, so its 3 August starts at 2 August 11:00 UTC.
    $day = LocalDay::fromDateAndOffset('2026-08-03', 780);

    expect($day->start->toIso8601String())->toBe('2026-08-02T11:00:00+00:00')
        ->and($day->end->toIso8601String())->toBe('2026-08-03T10:59:59+00:00');
});

test('a negative offset shifts the window forward from UTC midnight', function () {
    // Los Angeles is UTC-7 in summer: its 3 August runs to 4 August 06:59:59 UTC.
    $day = LocalDay::fromDateAndOffset('2026-08-03', -420);

    expect($day->start->toIso8601String())->toBe('2026-08-03T07:00:00+00:00')
        ->and($day->end->toIso8601String())->toBe('2026-08-04T06:59:59+00:00');
});

test('the tablet headers are read from the request', function () {
    $day = LocalDay::tryFromRequest(requestWithLocalDay('2026-08-03', '120'));

    expect($day)->not->toBeNull()
        ->and($day->start->toIso8601String())->toBe('2026-08-02T22:00:00+00:00');
});

test('a request without the headers reports no known day', function () {
    // Older app builds: callers keep their previous behaviour rather than guessing.
    expect(LocalDay::tryFromRequest(requestWithLocalDay(null, null)))->toBeNull()
        ->and(LocalDay::tryFromRequest(requestWithLocalDay('2026-08-03', null)))->toBeNull()
        ->and(LocalDay::tryFromRequest(requestWithLocalDay(null, '120')))->toBeNull();
});

test('nonsense headers are ignored rather than trusted', function () {
    expect(LocalDay::tryFromRequest(requestWithLocalDay('2026-13-45', '120')))->toBeNull()
        ->and(LocalDay::tryFromRequest(requestWithLocalDay('not-a-date', '120')))->toBeNull()
        ->and(LocalDay::tryFromRequest(requestWithLocalDay('2026-08-03', 'abc')))->toBeNull()
        // Beyond the real -12:00 .. +14:00 range.
        ->and(LocalDay::tryFromRequest(requestWithLocalDay('2026-08-03', '2000')))->toBeNull();
});

test('an explicit date wins over the header date', function () {
    // The schedule view asks for another day through ?date=, still in the tablet's timezone.
    $day = LocalDay::tryFromRequest(
        requestWithLocalDay('2026-08-03', '120'),
        Carbon::parse('2026-08-05')
    );

    expect($day->start->toIso8601String())->toBe('2026-08-04T22:00:00+00:00');
});

test('overlap keeps a meeting that runs across midnight', function () {
    $day = LocalDay::fromDateAndOffset('2026-08-03', 0);

    // Ends just inside the day.
    expect($day->overlaps(Carbon::parse('2026-08-02T23:00:00Z'), Carbon::parse('2026-08-03T00:30:00Z')))->toBeTrue()
        // Starts just inside the day.
        ->and($day->overlaps(Carbon::parse('2026-08-03T23:30:00Z'), Carbon::parse('2026-08-04T00:30:00Z')))->toBeTrue()
        // Wholly the day before.
        ->and($day->overlaps(Carbon::parse('2026-08-02T09:00:00Z'), Carbon::parse('2026-08-02T10:00:00Z')))->toBeFalse()
        // Wholly the day after — this is the booking that used to show up as "Next".
        ->and($day->overlaps(Carbon::parse('2026-08-04T08:00:00Z'), Carbon::parse('2026-08-04T09:00:00Z')))->toBeFalse();
});

test('providers are still asked for a day either side of the window', function () {
    $day = LocalDay::fromDateAndOffset('2026-08-03', 0);
    [$start, $end] = $day->fetchRange();

    expect($start->toIso8601String())->toBe('2026-08-02T00:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2026-08-04T23:59:59+00:00');
});

test('offsets that are not whole hours land on the right boundary', function () {
    // India is UTC+5:30 and Nepal UTC+5:45, so an hours-only offset would put the day boundary
    // 30 to 45 minutes out — exactly the edge this window is meant to get right.
    expect(LocalDay::fromDateAndOffset('2026-08-03', 330)->start->toIso8601String())
        ->toBe('2026-08-02T18:30:00+00:00')
        ->and(LocalDay::fromDateAndOffset('2026-08-03', 345)->start->toIso8601String())
        ->toBe('2026-08-02T18:15:00+00:00')
        // Chatham Islands, UTC+12:45 — still inside the accepted range.
        ->and(LocalDay::fromDateAndOffset('2026-08-03', 765)->start->toIso8601String())
        ->toBe('2026-08-02T11:15:00+00:00')
        // Newfoundland, UTC-3:30.
        ->and(LocalDay::fromDateAndOffset('2026-08-03', -210)->start->toIso8601String())
        ->toBe('2026-08-03T03:30:00+00:00');
});

test('the accepted range spans the real world extremes', function () {
    $request = fn (string $offset) => requestWithLocalDay('2026-08-03', $offset);

    // Baker Island -12:00 through Line Islands +14:00, including the quarter-hour zones between.
    expect(LocalDay::tryFromRequest($request('-720')))->not->toBeNull()
        ->and(LocalDay::tryFromRequest($request('840')))->not->toBeNull()
        ->and(LocalDay::tryFromRequest($request('330')))->not->toBeNull()
        ->and(LocalDay::tryFromRequest($request('765')))->not->toBeNull()
        // Outside anything that exists.
        ->and(LocalDay::tryFromRequest($request('-721')))->toBeNull()
        ->and(LocalDay::tryFromRequest($request('841')))->toBeNull();
});
