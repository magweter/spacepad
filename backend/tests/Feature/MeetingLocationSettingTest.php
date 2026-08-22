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

    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
});

test('the schedule leaves the location out until someone asks for it', function () {
    expect(DisplaySettings::getShowMeetingLocation($this->display))->toBeFalse();
});

test('the setting survives a round trip through the display section', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::DISPLAY,
        ]), ['timeline_widget_mode' => 'none', 'show_meeting_location' => '1'])
        ->assertRedirect(route('displays.configure', $this->display));

    expect(DisplaySettings::getShowMeetingLocation($this->display->fresh()))->toBeTrue();

    // Section saves post every checkbox in the section; an unchecked box simply stays away.
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::DISPLAY,
        ]), ['timeline_widget_mode' => 'none']);

    expect(DisplaySettings::getShowMeetingLocation($this->display->fresh()))->toBeFalse();
});

test('a profile can turn the location on for every display that follows it', function () {
    $profile = DisplayProfile::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Shared-building screens',
    ]);
    ProfileSettings::set($profile, 'show_meeting_location', true, 'boolean');

    $this->display->update(['display_profile_id' => $profile->id]);

    expect(DisplaySettings::getShowMeetingLocation($this->display->fresh()))->toBeTrue();
});

test('the setting reaches the tablet', function () {
    DisplaySettings::setShowMeetingLocation($this->display, true);

    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
    ]);

    $settings = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0.settings');

    expect($settings['show_meeting_location'])->toBeTrue();
});
