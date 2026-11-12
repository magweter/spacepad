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

test('a display starts on the classic theme', function () {
    expect(DisplaySettings::getTheme($this->display))->toBe('classic');
});

test('the theme survives a round trip through the branding section', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::BRANDING,
        ]), [
            'font_family' => 'Inter',
            'theme' => 'glass',
        ])
        ->assertRedirect(route('displays.configure', $this->display));

    expect(DisplaySettings::getTheme($this->display->fresh()))->toBe('glass');
});

test('an unknown theme is rejected', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', [
            'display' => $this->display,
            'section' => DisplaySettingSections::BRANDING,
        ]), ['theme' => 'neon'])
        ->assertSessionHasErrors('theme');

    expect(DisplaySettings::getTheme($this->display->fresh()))->toBe('classic');
});

test('the setter refuses an unknown theme', function () {
    expect(DisplaySettings::setTheme($this->display, 'neon'))->toBeFalse()
        ->and(DisplaySettings::getTheme($this->display->fresh()))->toBe('classic');
});

test('a profile sets the theme for every display that follows it', function () {
    $this->actingAs($this->user)
        ->post(route('profiles.store'), [
            'name' => 'Glass rooms',
            'theme' => 'glass',
        ])
        ->assertSessionHasNoErrors();

    $profile = DisplayProfile::where('name', 'Glass rooms')->firstOrFail();
    expect(ProfileSettings::get($profile, 'theme'))->toBe('glass');

    $this->display->update(['display_profile_id' => $profile->id]);

    expect(DisplaySettings::getTheme($this->display->fresh()))->toBe('glass');
});

test('a profile without a theme keeps the classic one', function () {
    $this->actingAs($this->user)
        ->post(route('profiles.store'), ['name' => 'Plain rooms'])
        ->assertSessionHasNoErrors();

    $profile = DisplayProfile::where('name', 'Plain rooms')->firstOrFail();
    expect(ProfileSettings::get($profile, 'theme'))->toBe('classic');
});

test('the theme reaches the tablet', function () {
    $device = Device::factory()->create([
        'user_id' => $this->user->id,
        'workspace_id' => $this->workspace->id,
        'display_id' => $this->display->id,
    ]);

    $settings = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0.settings');
    expect($settings['theme'])->toBe('classic');

    DisplaySettings::setTheme($this->display, 'glass');

    $settings = $this->actingAs($device)->getJson('/api/displays')->assertOk()->json('data.0.settings');
    expect($settings['theme'])->toBe('glass');
});

test('the configure page offers the themes with the current one selected', function () {
    DisplaySettings::setTheme($this->display, 'glass');

    $html = $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertOk()
        ->assertSee('Classic (default)')
        ->getContent();

    expect($html)->toMatch('/name="theme" value="glass"[^>]*checked/')
        ->and($html)->not->toMatch('/name="theme" value="classic"[^>]*checked/');
});

test('the profile form offers the themes', function () {
    $this->actingAs($this->user)
        ->get(route('profiles.create'))
        ->assertOk()
        ->assertSee('name="theme" value="glass"', false)
        ->assertSee(DisplaySettings::THEME_DESCRIPTIONS['glass']);
});
