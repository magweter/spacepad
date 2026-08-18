<?php

use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Helpers\DisplaySettingSections;
use App\Helpers\ProfileSettings;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create(); // Pro
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);

    // A display name that identifies the display, and a room name deliberately shared with
    // its neighbours — the building-name setup that made both lists unreadable.
    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'name' => 'Boardroom tablet',
        'display_name' => 'Willemsplein 3',
    ]);
});

test('both names can be changed after the display was created', function () {
    $this->actingAs($this->user)
        ->put(route('displays.update', $this->display), [
            'name' => 'Boardroom tablet 2',
            'display_name' => 'Willemsplein 5',
        ])
        ->assertRedirect(route('displays.configure', $this->display));

    $fresh = $this->display->fresh();

    expect($fresh->name)->toBe('Boardroom tablet 2')
        ->and($fresh->display_name)->toBe('Willemsplein 5');
});

test('renaming a display requires both names', function () {
    $this->actingAs($this->user)
        ->put(route('displays.update', $this->display), ['name' => 'Only this one'])
        ->assertSessionHasErrors('display_name');

    expect($this->display->fresh()->name)->toBe('Boardroom tablet');
});

test('a display in another workspace cannot be renamed', function () {
    $other = User::factory()->active()->unlimited()->create();
    $foreign = Display::factory()->create([
        'workspace_id' => $other->primaryWorkspace()->id,
        'user_id' => $other->id,
        'name' => 'Not yours',
    ]);

    $this->actingAs($this->user)
        ->put(route('displays.update', $foreign), [
            'name' => 'Mine now',
            'display_name' => 'Mine now',
        ])
        ->assertForbidden();

    expect($foreign->fresh()->name)->toBe('Not yours');
});

test('the room name shows by default and survives a round trip through the display section', function () {
    expect(DisplaySettings::getShowRoomName($this->display))->toBeTrue();

    // Section saves post every checkbox in the section; an unchecked box simply stays away.
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::DISPLAY,
        ]), ['timeline_widget_mode' => 'none'])
        ->assertRedirect(route('displays.configure', $this->display));

    expect(DisplaySettings::getShowRoomName($this->display->fresh()))->toBeFalse();

    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::DISPLAY,
        ]), ['timeline_widget_mode' => 'none', 'show_room_name' => '1']);

    expect(DisplaySettings::getShowRoomName($this->display->fresh()))->toBeTrue();
});

test('a profile can turn the room name off for every display that follows it', function () {
    $profile = DisplayProfile::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Lobby screens',
    ]);
    ProfileSettings::set($profile, 'show_room_name', false, 'boolean');

    $this->display->update(['display_profile_id' => $profile->id]);

    expect(DisplaySettings::getShowRoomName($this->display->fresh()))->toBeFalse();
});

test('the tablet is handed the display name alongside the room name', function () {
    DisplaySettings::setShowRoomName($this->display, false);

    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
    ]);

    $data = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0');

    // 'name' stays the room name: tablets already in the field read the header corner from it.
    expect($data['name'])->toBe('Willemsplein 3')
        ->and($data['dashboard_name'])->toBe('Boardroom tablet')
        ->and($data['settings']['show_room_name'])->toBeFalse();
});

test('the used-by list on a profile names the display, not the shared room', function () {
    $profile = DisplayProfile::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Meeting rooms',
    ]);
    $this->display->update(['display_profile_id' => $profile->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.edit', $profile))
        ->assertOk()
        ->assertSee('Boardroom tablet')
        ->assertDontSee('Willemsplein 3');
});
