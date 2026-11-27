<?php

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.events.cache_enabled' => true, 'settings.is_self_hosted' => false]);
});

function warmDisplay(User $user, array $attributes = []): Display
{
    return Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'status' => DisplayStatus::ACTIVE] + $attributes);
}

test('only displays in use are warmed: a recent tablet or a board', function () {
    $user = User::factory()->active()->create();
    $withTablet = warmDisplay($user);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'display_id' => $withTablet->id, 'last_activity_at' => now()->subHour()]);
    $onBoard = warmDisplay($user);
    $board = Board::factory()->create(['workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id, 'show_all_displays' => false]);
    $board->displays()->attach($onBoard->id);
    $unused = warmDisplay($user);
    $paused = warmDisplay($user);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'display_id' => $paused->id, 'last_activity_at' => now()]);
    $paused->update(['status' => DisplayStatus::REQUIRES_PRO]);

    $warmed = [];
    $this->mock(EventService::class)->shouldReceive('warmCache')->andReturnUsing(function (Display $d) use (&$warmed) {
        $warmed[] = $d->id;

        return true;
    });

    $this->artisan('app:warm-event-caches')->assertSuccessful();

    expect($warmed)->toEqualCanonicalizing([$withTablet->id, $onBoard->id])
        ->and($warmed)->not->toContain($unused->id)
        ->and($warmed)->not->toContain($paused->id);
});

test('a board showing every display warms all of the workspace', function () {
    $user = User::factory()->active()->create();
    $a = warmDisplay($user);
    $b = warmDisplay($user);
    Board::factory()->create(['workspace_id' => $user->primaryWorkspace()->id, 'user_id' => $user->id, 'show_all_displays' => true]);

    $warmed = [];
    $this->mock(EventService::class)->shouldReceive('warmCache')->andReturnUsing(function (Display $d) use (&$warmed) {
        $warmed[] = $d->id;

        return true;
    });

    $this->artisan('app:warm-event-caches')->assertSuccessful();

    expect($warmed)->toEqualCanonicalizing([$a->id, $b->id]);
});

test('a display whose cache is filled is not asked again', function () {
    $user = User::factory()->active()->create();
    $display = warmDisplay($user);
    Cache::put($display->getEventsCacheKey().':fallback', collect(), now()->addMinutes(2));

    expect(app(EventService::class)->warmCache($display))->toBeFalse();
});

test('with caching off nothing is warmed', function () {
    config(['services.events.cache_enabled' => false]);
    $user = User::factory()->active()->create();

    expect(app(EventService::class)->warmCache(warmDisplay($user)))->toBeFalse();
    $this->artisan('app:warm-event-caches')->expectsOutputToContain('nothing to warm')->assertSuccessful();
});
