<?php

use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * The portal pages read the event cache and nothing else: a slow calendar provider must never
 * hold up a page someone is waiting for. The cache is kept filled by app:warm-event-caches.
 */
beforeEach(function () {
    config(['services.events.cache_enabled' => true, 'settings.is_self_hosted' => false]);
    Http::preventStrayRequests();
});

test('the overview, displays and boards pages never ask a calendar', function (string $route) {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $workspace->forceFill(['is_unlimited' => true])->save();
    Display::factory()->count(3)->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $this->mock(EventService::class)->shouldNotReceive('getEventsForDisplay');

    $this->actingAs($user)->get(route($route))->assertOk();
})->with(['dashboard', 'displays.index', 'boards.index']);
