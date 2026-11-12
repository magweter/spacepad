<?php

use App\Console\Commands\SeedDemo;
use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it sets up an account, a display and a paired tablet', function () {
    $this->artisan('spacepad:demo')->assertSuccessful();

    $user = User::where('email', 'demo@spacepad.test')->firstOrFail();
    $workspace = $user->primaryWorkspace();
    $display = Display::where('user_id', $user->id)->firstOrFail();
    $device = Device::where('user_id', $user->id)->firstOrFail();

    expect($workspace->hasPro())->toBeTrue()
        ->and($display->status)->toBe(DisplayStatus::ACTIVE)
        ->and($display->workspace_id)->toBe($workspace->id)
        ->and($device->display_id)->toBe($display->id)
        ->and($device->workspace_id)->toBe($workspace->id)
        ->and(DisplaySettings::isBookingEnabled($display))->toBeTrue()
        ->and(DisplaySettings::getTimelineWidgetMode($display))->toBe('side_panel');
});

test('a second run keeps the settings changed in the portal', function () {
    $this->artisan('spacepad:demo')->assertSuccessful();
    DisplaySettings::setBookingEnabled(Display::firstOrFail(), false);

    $this->artisan('spacepad:demo')->assertSuccessful();

    expect(DisplaySettings::isBookingEnabled(Display::firstOrFail()))->toBeFalse();
});

test('the tablet can fetch its display with the printed token, and sees a meeting running now', function () {
    $this->artisan('spacepad:demo')->assertSuccessful();

    $device = Device::where('uid', 'spacepad-demo-tablet')->firstOrFail();
    $token = $device->createToken('device-token')->plainTextToken;

    $events = $this->withToken($token)
        ->getJson("/api/displays/{$device->display_id}/events")
        ->assertOk()
        ->json('data');

    $running = collect($events)->first(fn ($e) => now()->between($e['start'], $e['end']));

    expect($running)->not->toBeNull();
});

test('running it again reuses the account and moves the meetings along', function () {
    $this->artisan('spacepad:demo')->assertSuccessful();
    $this->travel(3)->hours();
    $this->artisan('spacepad:demo')->assertSuccessful();

    expect(User::where('email', 'demo@spacepad.test')->count())->toBe(1)
        ->and(Display::count())->toBe(1)
        ->and(Device::count())->toBe(1)
        ->and(Event::where('start', '<=', now())->where('end', '>', now())->count())->toBe(1);
});

test('it refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('spacepad:demo')->assertFailed();

    expect(User::where('email', 'demo@spacepad.test')->exists())->toBeFalse();
});

test('the printed command survives the shell', function () {
    $command = SeedDemo::flutterCommand('http://localhost:8000', '12|secret', 'display-1');

    // Let a shell parse the line and echo back the arguments it would hand to flutter.
    $parsed = shell_exec('printf "%s\\n" '.substr($command, strlen('fvm flutter run ')));

    expect($parsed)->toBe("--dart-define=DEMO_API_URL=http://localhost:8000\n--dart-define=DEMO_TOKEN=12|secret\n--dart-define=DEMO_DISPLAY_ID=display-1\n");
});
