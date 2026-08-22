<?php

use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\DisplayProfileSetting;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_profile_id' => $this->profile->id,
    ]);
});

function setProfileSetting(DisplayProfile $profile, string $key, mixed $value, string $type = 'string'): void
{
    DisplayProfileSetting::updateOrCreate(
        ['display_profile_id' => $profile->id, 'key' => $key],
        ['value' => $value, 'type' => $type],
    );
}

test('unset setting falls back to the provided default', function () {
    expect(DisplaySettings::getSetting($this->display, 'text_available', 'Free'))->toBe('Free');
});

test('a linked display inherits its profile setting (live link)', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');

    expect(DisplaySettings::getSetting($this->display->fresh(), 'text_available'))->toBe('Vrij');
});

test('a display without a profile does not inherit anything', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');
    $this->display->update(['display_profile_id' => null]);

    expect(DisplaySettings::getSetting($this->display->fresh(), 'text_available', 'Free'))->toBe('Free');
});

test('an own setting overrides the profile value', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');
    DisplaySettings::setSetting($this->display, 'text_available', 'Beschikbaar');

    expect(DisplaySettings::getSetting($this->display->fresh(), 'text_available'))->toBe('Beschikbaar');
});

test('removing the override falls back to the profile value', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');
    DisplaySettings::setSetting($this->display, 'text_available', 'Beschikbaar');
    DisplaySettings::deleteSetting($this->display, 'text_available');

    expect(DisplaySettings::getSetting($this->display->fresh(), 'text_available'))->toBe('Vrij');
});

test('a boolean false override wins over a true profile value', function () {
    setProfileSetting($this->profile, 'booking_enabled', true, 'boolean');
    DisplaySettings::setSetting($this->display, 'booking_enabled', false, 'boolean');

    expect(DisplaySettings::getSetting($this->display->fresh(), 'booking_enabled', 'x'))->toBe(false);
});

test('resolution works with eager-loaded relations (no N+1 path)', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');
    DisplaySettings::setSetting($this->display, 'font_family', 'Roboto');

    $display = Display::with(['settings', 'profile.settings'])->find($this->display->id);

    expect(DisplaySettings::getSetting($display, 'text_available'))->toBe('Vrij')       // from profile
        ->and(DisplaySettings::getSetting($display, 'font_family'))->toBe('Roboto');    // own override
});

test('getAllSettings merges profile base with own overrides', function () {
    setProfileSetting($this->profile, 'text_available', 'Vrij');
    setProfileSetting($this->profile, 'font_family', 'Inter');
    DisplaySettings::setSetting($this->display, 'font_family', 'Roboto');
    DisplaySettings::setSetting($this->display, 'border_thickness', 'large');

    $all = DisplaySettings::getAllSettings($this->display->fresh());

    expect($all)->toMatchArray([
        'text_available' => 'Vrij',      // inherited from profile
        'font_family' => 'Roboto',       // overridden by display
        'border_thickness' => 'large',   // display-only
    ]);
});

test('convenience getters respect profile inheritance', function () {
    setProfileSetting($this->profile, 'booking_enabled', true, 'boolean');

    expect($this->display->fresh()->isBookingEnabled())->toBeTrue();
});
