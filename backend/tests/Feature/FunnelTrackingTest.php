<?php

use App\Enums\UsageType;
use App\Enums\UserStatus;
use App\Models\Board;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CalDAVService;
use App\Services\FunnelTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Facade;
use Spatie\GoogleTagManager\GoogleTagManagerFacade;

uses(RefreshDatabase::class);

/**
 * Google Tag Manager belongs on the acquisition funnel and nowhere else.
 *
 * It used to load on every page that extends the blank layout, boards included. A board
 * hangs on a wall and reloads all day, so one tv produced more pageviews than every real
 * visitor put together and the funnel numbers meant nothing.
 */
beforeEach(function () {
    config()->set('googletagmanager.enabled', true);
    config()->set('googletagmanager.id', 'GTM-TEST123');
});

/**
 * Drop the data layer built up during the previous request.
 *
 * The GTM instance is a scoped binding, so a real request always starts with an empty one.
 * Test requests share a container, so without this the events of the request before are
 * still on the object: the middleware writes them back to the session every time and a
 * flashed event would look like it repeats forever. Call this between requests whenever a
 * test cares about which page fired what.
 */
function nextRequest(): void
{
    app()->forgetScopedInstances();
    Facade::clearResolvedInstances();
}

/**
 * @return array{0: User, 1: Workspace}
 */
function activeUserWithWorkspace(): array
{
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    return [$user, $workspace];
}

test('a public board does not load analytics', function () {
    $board = Board::factory()->create([
        'is_public' => true,
        'public_token' => 'a-public-board-token',
    ]);

    $this->get('/b/a-public-board-token')
        ->assertOk()
        ->assertDontSee('gtm.js', false)
        ->assertDontSee('GTM-TEST123', false);
});

test('a board opened from the dashboard does not load analytics', function () {
    [$user, $workspace] = activeUserWithWorkspace();

    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('boards.show', $board))
        ->assertOk()
        ->assertDontSee('gtm.js', false);
});

test('everyday use of the app is not tracked', function () {
    [$user] = activeUserWithWorkspace();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('gtm.js', false);
});

test('the login and register pages are tracked', function () {
    $this->get(route('login'))->assertOk()->assertSee('gtm.js', false);
    $this->get(route('register'))->assertOk()->assertSee('gtm.js', false);
});

test('the onboarding page is tracked', function () {
    $user = User::factory()->create(['usage_type' => null]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSee('gtm.js', false);
});

test('nothing is tracked while GTM is switched off', function () {
    config()->set('googletagmanager.enabled', false);

    $this->get(route('login'))->assertOk()->assertDontSee('gtm.js', false);
});

test('a queued event loads analytics on an otherwise untracked page', function () {
    [$user] = activeUserWithWorkspace();

    $this->actingAs($user)
        ->get(route('billing.thanks'))
        ->assertRedirect(route('dashboard'));

    // The purchase is the conversion, so it has to arrive even though the dashboard it lands
    // on is not itself part of the funnel.
    nextRequest();
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('gtm.js', false)
        ->assertSee('purchase', false);

    // And only for that one request.
    nextRequest();
    $this->get(route('dashboard'))->assertDontSee('gtm.js', false);
});

test('the sign up event survives the redirect after registering', function () {
    // The middleware that carries data layer events across a redirect used to run on
    // authenticated routes only, so sign_up — pushed by a guest — never reached the session.
    $this->post(route('register.store'), [
        'name' => 'Nieuwe Klant',
        'email' => 'nieuw@example.com',
    ])->assertRedirect();

    nextRequest();
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('sign_up', false);
});

test('connecting a calendar account reports a milestone, for the first one only', function () {
    // Not the active() state: that seeds an Outlook account, so the workspace would already
    // be past this step before the test starts.
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'usage_type' => UsageType::PERSONAL,
        'terms_accepted_at' => now(),
    ]);

    $this->mock(CalDAVService::class)
        ->shouldReceive('checkConnection')
        ->andReturn(['success' => true, 'message' => 'ok']);

    $connect = fn (string $username) => $this->actingAs($user)->post(route('caldav-accounts.store'), [
        'url' => 'https://caldav.example.com/',
        'username' => $username,
        'password' => 'secret',
    ]);

    $connect('first@example.com')->assertRedirect(route('dashboard'));

    nextRequest();
    $this->get(route('dashboard'))->assertSee('calendar_connected', false);

    // A second account is not a funnel step: they are already past it.
    nextRequest();
    $connect('second@example.com')->assertRedirect(route('dashboard'));

    nextRequest();
    $this->get(route('dashboard'))->assertDontSee('calendar_connected', false);
});

test('a milestone fires for the first display only', function () {
    [$user, $workspace] = activeUserWithWorkspace();

    FunnelTracking::displayCreated($workspace);
    expect(gtmEvents())->toContain('display_created');

    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    GoogleTagManagerFacade::clear();
    FunnelTracking::displayCreated($workspace);
    expect(gtmEvents())->not->toContain('display_created');
});

test('the device paired milestone fires once for a freshly paired tablet', function () {
    [$user, $workspace] = activeUserWithWorkspace();

    Device::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'uid' => 'fresh-tablet',
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('device_paired', false);

    nextRequest();
    $this->get(route('dashboard'))->assertDontSee('device_paired', false);
});

test('a tablet paired long ago is not reported as a new milestone', function () {
    [$user, $workspace] = activeUserWithWorkspace();

    Device::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'uid' => 'old-tablet',
        'created_at' => now()->subWeek(),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('device_paired', false);
});

/**
 * The events queued on the data layer for the current request.
 *
 * @return array<int, string>
 */
function gtmEvents(): array
{
    return GoogleTagManagerFacade::getFlashPushData()
        ->merge(GoogleTagManagerFacade::getPushData())
        ->map(fn ($item) => $item->toArray()['event'] ?? null)
        ->filter()
        ->values()
        ->all();
}
