<?php

use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false, 'services.events.cache_enabled' => true]);
});

function displaysPageDisplay(User $user, array $attributes = []): Display
{
    return Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id] + $attributes);
}

test('guests are sent to the login page', function () {
    $this->get(route('displays.index'))->assertRedirect(route('login'));
});

test('the page lists the workspace displays with their calendar, tablets and bookings', function () {
    $user = User::factory()->active()->create();
    $display = displaysPageDisplay($user, ['name' => 'Boardroom', 'status' => DisplayStatus::ACTIVE]);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'display_id' => $display->id, 'name' => 'Lobby iPad', 'last_activity_at' => now()->subMinute()]);
    Cache::put($display->getEventsCacheKey(), collect([
        new Event(['summary' => 'Design sync', 'start' => now()->subMinutes(10), 'end' => now()->addMinutes(20), 'source' => 'outlook']),
    ]));

    // Someone else's display never shows up.
    displaysPageDisplay(User::factory()->active()->create());

    $this->actingAs($user)->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Displays/Index')
            ->where('shell.nav.2.active', true)
            ->has('displays', 1)
            ->where('displays.0.name', 'Boardroom')
            ->where('displays.0.status', 'active')
            ->where('displays.0.devices.0.name', 'Lobby iPad')
            ->where('displays.0.devices.0.online', true)
            ->where('displays.0.device.online', true)
            ->where('displays.0.hasData', true)
            ->where('displays.0.events.0.title', 'Design sync')
            ->where('displays.0.urls.configure', route('displays.configure', $display))
            ->where('displays.0.urls.status', route('displays.updateStatus', $display))
            ->has('connectCode')
            ->where('urls.newDisplay', route('setup.first-display', ['step' => 'room', 'new' => 1])));
});

test('a tablet that has been quiet for a while counts as offline', function () {
    $user = User::factory()->active()->create();
    $display = displaysPageDisplay($user);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'display_id' => $display->id, 'last_activity_at' => now()->subHour()]);

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page->where('displays.0.devices.0.online', false)->where('displays.0.hasData', false));
});

test('with no displays the page shows the empty state and links to the first display setup', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('displays', [])
            ->where('connectCode', null)
            ->where('urls.newDisplay', route('setup.first-display')));
});

test('a deep link opens the display panel', function () {
    $user = User::factory()->active()->create();
    $display = displaysPageDisplay($user);

    $this->actingAs($user)->get(route('displays.index', ['display' => $display->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected', $display->id));
});

test('the free plan cannot add a second display, bulk assign or see profiles', function () {
    $user = User::factory()->active()->create();
    displaysPageDisplay($user, ['status' => DisplayStatus::ACTIVE]);
    displaysPageDisplay($user, ['status' => DisplayStatus::REQUIRES_PRO]);

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasPro', false)
            ->where('mustUpgrade', true)
            ->where('canBulkAssign', false)
            ->where('profiles', [])
            ->where('displays', fn ($displays) => collect($displays)->pluck('status')->contains('requires_pro')));
});

test('a Pro workspace with several displays can bulk assign and sees its profiles and deviations', function () {
    $user = User::factory()->active()->unlimited()->create();
    $profile = DisplayProfile::factory()->create(['workspace_id' => $user->primaryWorkspace()->id, 'name' => 'Meeting rooms']);
    $linked = displaysPageDisplay($user, ['name' => 'A room', 'display_profile_id' => $profile->id]);
    $own = displaysPageDisplay($user, ['name' => 'B room']);
    DisplaySettings::setSetting($own, 'text_available', 'Eigen tekst');

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasPro', true)
            ->where('mustUpgrade', false)
            ->where('canBulkAssign', true)
            ->where('showBulkHint', false)
            ->where('profiles.0.name', 'Meeting rooms')
            ->where('displays.0.profile.name', 'Meeting rooms')
            ->where('displays.0.profile.deviates', false)
            ->where('displays.1.profile', null)
            ->where('displays.1.hasOwnSettings', true));

    // A linked display with its own value in a section deviates from its profile.
    DisplaySettings::setSetting($linked, 'font_family', 'Lato');

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page->where('displays.0.profile.deviates', true));
});

test('the bulk how-to shows until a profile has been assigned anywhere', function () {
    $user = User::factory()->active()->unlimited()->create();
    displaysPageDisplay($user);
    displaysPageDisplay($user);

    $this->actingAs($user)->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canBulkAssign', true)->where('showBulkHint', true));
});

test('pausing and deleting land back on the displays page', function () {
    $user = User::factory()->active()->unlimited()->create();
    $display = displaysPageDisplay($user, ['status' => DisplayStatus::ACTIVE]);

    $this->actingAs($user)
        ->patch(route('displays.updateStatus', $display), ['status' => 'deactivated'])
        ->assertRedirect(route('displays.index', ['display' => $display->id]));
    expect($display->fresh()->status)->toBe(DisplayStatus::DEACTIVATED);

    $this->actingAs($user)
        ->delete(route('displays.delete', $display))
        ->assertRedirect(route('displays.index'));
    expect(Display::find($display->id))->toBeNull();
});

test('someone outside the workspace cannot change or delete a display', function () {
    $user = User::factory()->active()->create();
    $display = displaysPageDisplay($user, ['status' => DisplayStatus::ACTIVE]);
    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)->patch(route('displays.updateStatus', $display), ['status' => 'deactivated'])->assertForbidden();
    $this->actingAs($outsider)->delete(route('displays.delete', $display))->assertForbidden();

    expect($display->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});
