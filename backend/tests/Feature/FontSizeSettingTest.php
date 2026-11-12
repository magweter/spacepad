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

test('every text starts at the normal size', function () {
    foreach (array_keys(DisplaySettings::FONT_SIZE_ELEMENTS) as $element) {
        expect(DisplaySettings::getFontSize($this->display, $element))->toBe('normal');
    }
});

test('the sizes survive a round trip through the branding section', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::BRANDING,
        ]), [
            'font_family' => 'Inter',
            'font_size_room_name' => 'xlarge',
            'font_size_clock' => 'large',
            'font_size_title' => 'large',
            'font_size_details' => 'small',
            'font_size_buttons' => 'large',
            'font_size_toolbar' => 'small',
            'font_size_calendar' => 'normal',
        ])
        ->assertRedirect(route('displays.configure', $this->display));

    $display = $this->display->fresh();

    expect(DisplaySettings::getFontSize($display, 'room_name'))->toBe('xlarge')
        ->and(DisplaySettings::getFontSize($display, 'clock'))->toBe('large')
        ->and(DisplaySettings::getFontSize($display, 'title'))->toBe('large')
        ->and(DisplaySettings::getFontSize($display, 'details'))->toBe('small')
        ->and(DisplaySettings::getFontSize($display, 'buttons'))->toBe('large')
        ->and(DisplaySettings::getFontSize($display, 'toolbar'))->toBe('small')
        ->and(DisplaySettings::getFontSize($display, 'calendar'))->toBe('normal');
});

test('an unknown size is rejected', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::BRANDING,
        ]), ['font_size_room_name' => 'huge'])
        ->assertSessionHasErrors('font_size_room_name');

    expect(DisplaySettings::getFontSize($this->display->fresh(), 'room_name'))->toBe('normal');
});

test('a profile sets the sizes for every display that follows it', function () {
    $this->actingAs($this->user)
        ->post(route('profiles.store'), [
            'name' => 'Reception screens',
            'font_size_room_name' => 'xlarge',
            'font_size_title' => 'large',
        ])
        ->assertSessionHasNoErrors();

    $profile = DisplayProfile::where('name', 'Reception screens')->firstOrFail();
    expect(ProfileSettings::get($profile, 'font_size_room_name'))->toBe('xlarge')
        ->and(ProfileSettings::get($profile, 'font_size_calendar'))->toBe('normal');

    $this->display->update(['display_profile_id' => $profile->id]);

    expect(DisplaySettings::getFontSize($this->display->fresh(), 'room_name'))->toBe('xlarge')
        ->and(DisplaySettings::getFontSize($this->display->fresh(), 'title'))->toBe('large');
});

test('the sizes reach the tablet', function () {
    DisplaySettings::setFontSize($this->display, 'room_name', 'xlarge');

    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
    ]);

    $settings = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0.settings');

    expect($settings['font_size_room_name'])->toBe('xlarge')
        ->and($settings['font_size_buttons'])->toBe('normal');

    foreach (['clock', 'title', 'details', 'toolbar', 'calendar'] as $element) {
        expect($settings["font_size_{$element}"])->toBe('normal');
    }
});
