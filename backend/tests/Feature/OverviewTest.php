<?php

use App\Enums\DisplayStatus;
use App\Enums\PermissionType;
use App\Enums\WorkspaceRole;
use App\Models\Device;
use App\Models\Display;
use App\Models\Event;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false, 'services.events.cache_enabled' => true]);
});

function overviewDisplay(User $user, array $attributes = []): Display
{
    return Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id] + $attributes);
}

test('the overview shows each room with its bookings from the cache', function () {
    $user = User::factory()->active()->create();
    $display = overviewDisplay($user, ['display_name' => 'Boardroom']);
    Cache::put($display->getEventsCacheKey(), collect([
        new Event(['summary' => 'Design sync', 'start' => now()->subMinutes(10), 'end' => now()->addMinutes(20), 'source' => 'outlook']),
    ]));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overview/Index')
            ->where('rooms.0.name', 'Boardroom')
            ->where('rooms.0.hasData', true)
            ->where('rooms.0.events.0.title', 'Design sync')
            ->where('rooms.0.events.0.tablet', false));
});

test('a room whose tablet has not asked yet has no data, and the overview calls no calendar', function () {
    $user = User::factory()->active()->create();
    overviewDisplay($user);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('rooms.0.hasData', false)->where('rooms.0.events', []));
});

test('a tablet that has been quiet for a while counts as offline', function () {
    $user = User::factory()->active()->create();
    $display = overviewDisplay($user);
    Device::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'display_id' => $display->id, 'last_activity_at' => now()->subHour()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('rooms.0.device.online', false)->where('checklist.tablet', true));
});

test('only what blocks the product shows as a notice', function () {
    $user = User::factory()->active()->create();
    overviewDisplay($user);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('notices', []));

    overviewDisplay($user, ['status' => DisplayStatus::REQUIRES_PRO]);
    OutlookAccount::where('user_id', $user->id)->update(['permission_type' => PermissionType::WRITE, 'booking_method' => null]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notices', fn ($notices) => collect($notices)->pluck('tag')->contains('Paused')));
});

test('links to a tab of the old dashboard open that page', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('dashboard', ['tab' => 'boards']))->assertRedirect(route('boards.index'));
    $this->actingAs($user)->get(route('dashboard', ['tab' => 'displays']))->assertRedirect(route('displays.index'));
});

test('a flow that ends in a modal lands on the calendars page', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->withSession(['open-google-booking-method-modal' => 'abc'])
        ->get(route('dashboard'))
        ->assertRedirect(route('calendars.index'));
});

test('with caching off the events are fetched on the spot', function () {
    config(['services.events.cache_enabled' => false]);
    $user = User::factory()->active()->create();
    $display = overviewDisplay($user);

    $this->mock(EventService::class)->shouldReceive('getEventsForDisplay')->once()->with($display->id)
        ->andReturn(collect([new Event(['summary' => 'Planning', 'start' => now()->addHour(), 'end' => now()->addHours(2), 'source' => 'outlook'])]));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('rooms.0.hasData', true)->where('rooms.0.events.0.title', 'Planning'));
});

test('every portal page knows how this person can get Pro', function () {
    $owner = User::factory()->active()->create(['name' => 'Martijn Wetering']);
    $workspace = $owner->primaryWorkspace();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('upgrade.hasPro', false)
            ->where('upgrade.canManage', true)
            ->where('upgrade.trial', true)
            ->where('upgrade.salesEmail', config('settings.sales_email'))
            ->where('upgrade.urls.checkout', route('billing.checkout')));

    $this->actingAs($member)->withSession(['selected_workspace_id' => $workspace->id])->get(route('displays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('upgrade.canManage', false)
            ->where('upgrade.managers', ['Martijn']));
});
