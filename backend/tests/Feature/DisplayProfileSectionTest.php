<?php

use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Helpers\DisplaySettingSections;
use App\Helpers\ProfileSettings;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\DisplaySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create(); // Pro
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);

    // A profile that owns a value in three different sections.
    $this->profile = DisplayProfile::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Meeting rooms',
    ]);
    ProfileSettings::set($this->profile, 'booking_enabled', true, 'boolean');   // behavior
    ProfileSettings::set($this->profile, 'text_available', 'Vrij');             // texts
    ProfileSettings::set($this->profile, 'font_family', 'Roboto');              // branding

    $this->display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'display_profile_id' => $this->profile->id,
    ]);
});

/** Post a section save with the given payload. */
function saveSection($test, $display, string $section, array $payload = [])
{
    return $test->actingAs($test->user)
        ->put(route('displays.section.update', ['display' => $display, 'section' => $section]), $payload);
}

test('saving one section detaches only that section', function () {
    // Everything follows the profile to begin with.
    foreach (array_keys(DisplaySettingSections::all()) as $section) {
        expect(DisplaySettings::sectionFollowsProfile($this->display, $section))->toBeTrue();
    }

    saveSection($this, $this->display, DisplaySettingSections::BEHAVIOR, [
        'booking_enabled' => '0',
    ])->assertRedirect(route('displays.configure', $this->display));

    $fresh = $this->display->fresh();

    // The saved section is now the display's own...
    expect(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::BEHAVIOR))->toBeFalse()
        ->and(DisplaySettings::getSetting($fresh, 'booking_enabled'))->toBeFalse();

    // ...while every other section still tracks the profile. This is the regression guard: the old
    // controller wrote every key on every save, which silently broke the whole link.
    expect(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::TEXTS))->toBeTrue()
        ->and(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::BRANDING))->toBeTrue()
        ->and(DisplaySettings::getSetting($fresh, 'text_available'))->toBe('Vrij')
        ->and(DisplaySettings::getSetting($fresh, 'font_family'))->toBe('Roboto');
});

test('a detached section keeps following profile changes in other sections', function () {
    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    // Change the profile afterwards.
    ProfileSettings::set($this->profile, 'text_available', 'Beschikbaar');
    ProfileSettings::set($this->profile, 'font_family', 'Montserrat');

    $fresh = $this->display->fresh();

    expect(DisplaySettings::getSetting($fresh, 'text_available'))->toBe('Beschikbaar')  // still inherited
        ->and(DisplaySettings::getSetting($fresh, 'font_family'))->toBe('Lato');        // own value wins
});

test('resetting a section makes it follow the profile again without touching other sections', function () {
    saveSection($this, $this->display, DisplaySettingSections::BEHAVIOR, ['booking_enabled' => '0']);
    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    $this->actingAs($this->user)
        ->post(route('displays.section.reset', ['display' => $this->display, 'section' => DisplaySettingSections::BRANDING]))
        ->assertRedirect(route('displays.configure', $this->display));

    $fresh = $this->display->fresh();

    expect(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::BRANDING))->toBeTrue()
        ->and(DisplaySettings::getSetting($fresh, 'font_family'))->toBe('Roboto')
        // The other detached section is untouched.
        ->and(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::BEHAVIOR))->toBeFalse();
});

test('an unknown section is a 404', function () {
    $this->actingAs($this->user)
        ->put(route('displays.section.update', ['display' => $this->display, 'section' => 'nonsense']))
        ->assertNotFound();
});

test('a display without a profile never reports following one', function () {
    $solo = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    expect(DisplaySettings::sectionFollowsProfile($solo, DisplaySettingSections::BEHAVIOR))->toBeFalse()
        ->and(DisplaySettings::deviatesFromProfile($solo))->toBeFalse();
});

test('deviatesFromProfile only flags a display with its own values', function () {
    expect(DisplaySettings::deviatesFromProfile($this->display->fresh()))->toBeFalse();

    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    expect(DisplaySettings::deviatesFromProfile($this->display->fresh()))->toBeTrue();
});

test('a legacy calendar_enabled key counts as an own value for the display section', function () {
    // Older displays carry the renamed key; it must not look like the section follows the profile.
    DisplaySettings::setSetting($this->display, 'calendar_enabled', true, 'boolean');

    expect(DisplaySettings::sectionFollowsProfile($this->display->fresh(), DisplaySettingSections::DISPLAY))->toBeFalse();

    DisplaySettings::resetSection($this->display, DisplaySettingSections::DISPLAY);

    expect(DisplaySettings::sectionFollowsProfile($this->display->fresh(), DisplaySettingSections::DISPLAY))->toBeTrue();
});

test('bulk assigning a profile clears the selected displays own settings', function () {
    $other = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    DisplaySettings::setSetting($other, 'text_available', 'Eigen tekst');

    $this->actingAs($this->user)
        ->post(route('displays.profile.bulk'), [
            'display_ids' => [$other->id],
            'display_profile_id' => $this->profile->id,
        ])
        ->assertRedirect(route('dashboard', ['tab' => 'displays']));

    $fresh = $other->fresh();

    expect($fresh->display_profile_id)->toBe($this->profile->id)
        ->and(DisplaySetting::where('display_id', $other->id)->count())->toBe(0)
        // Now fully inheriting the profile.
        ->and(DisplaySettings::getSetting($fresh, 'text_available'))->toBe('Vrij');
});

test('bulk unlinking keeps the display values so nothing changes on the wall', function () {
    DisplaySettings::setSetting($this->display, 'text_available', 'Eigen tekst');

    $this->actingAs($this->user)
        ->post(route('displays.profile.bulk'), [
            'display_ids' => [$this->display->id],
            'display_profile_id' => '',
        ])
        ->assertRedirect(route('dashboard', ['tab' => 'displays']));

    $fresh = $this->display->fresh();

    expect($fresh->display_profile_id)->toBeNull()
        ->and(DisplaySettings::getSetting($fresh, 'text_available'))->toBe('Eigen tekst');
});

test('bulk assigning ignores displays from another workspace', function () {
    $stranger = User::factory()->active()->unlimited()->create();
    $foreign = Display::factory()->create([
        'workspace_id' => $stranger->primaryWorkspace()->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $this->actingAs($this->user)
        ->post(route('displays.profile.bulk'), [
            'display_ids' => [$foreign->id],
            'display_profile_id' => $this->profile->id,
        ]);

    expect($foreign->fresh()->display_profile_id)->toBeNull();
});

test('bulk assigning rejects a profile from another workspace', function () {
    $stranger = User::factory()->active()->unlimited()->create();
    $foreignProfile = DisplayProfile::factory()->create(['workspace_id' => $stranger->primaryWorkspace()->id]);

    $this->actingAs($this->user)
        ->post(route('displays.profile.bulk'), [
            'display_ids' => [$this->display->id],
            'display_profile_id' => $foreignProfile->id,
        ])
        ->assertSessionHasErrors('display_profile_id');
});

test('the displays overview shows the linked profile and flags a customised display', function () {
    // The profile sits as a chip under the display name; deviating adds a marker to its tooltip.
    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'displays']))
        ->assertOk()
        ->assertSee('Meeting rooms')
        ->assertDontSee('with its own settings in one or more sections');

    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'displays']))
        ->assertOk()
        ->assertSee('Meeting rooms')
        ->assertSee('with its own settings in one or more sections');
});

test('the bulk how-to shows until a profile has actually been assigned', function () {
    // Bulk controls only appear with more than one display.
    Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'display_profile_id' => null,
    ]);

    // The fixture display already follows a profile, so the how-to has served its purpose.
    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'displays']))
        ->assertOk()
        ->assertDontSee('Configure several displays at once');

    // With nothing assigned anywhere, it comes back.
    Display::where('workspace_id', $this->workspace->id)->update(['display_profile_id' => null]);

    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'displays']))
        ->assertOk()
        ->assertSee('Configure several displays at once');
});

test('hiding the how-to does not take the bulk assign controls with it', function () {
    Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'display_profile_id' => null,
    ]);

    // The how-to is onboarding; the bulk bar is the feature and must always stay.
    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'displays']))
        ->assertOk()
        ->assertDontSee('Configure several displays at once')
        ->assertSee('Assign profile')
        ->assertSee('Unlink');
});

test('the configuration screen shows a badge per section', function () {
    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    $html = $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertOk()
        ->getContent();

    // Four sections still follow the profile, branding does not. (Advertisement is feature-gated.)
    expect(substr_count($html, 'Follows profile'))->toBeGreaterThanOrEqual(3);
    expect($html)->toContain('Own settings');
    expect($html)->toContain('Follow profile');
});

test('a profile image is inherited by the displays that follow it', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->put(route('profiles.update', $this->profile), [
        'name' => $this->profile->name,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    $profilePath = ProfileSettings::get($this->profile->fresh(), 'logo');

    expect($profilePath)->toStartWith('profiles/logos/');
    Storage::disk('public')->assertExists($profilePath);

    // The display has no logo of its own, so it resolves to the profile's file.
    expect(DisplaySettings::getSetting($this->display->fresh(), 'logo'))->toBe($profilePath)
        ->and(DisplaySettings::getOwnSetting($this->display->fresh(), 'logo'))->toBeNull();
});

test('removing an inherited image on a display never deletes the profile file', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->put(route('profiles.update', $this->profile), [
        'name' => $this->profile->name,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    $profilePath = ProfileSettings::get($this->profile->fresh(), 'logo');

    // Saving the branding section on the display, asking to remove the (inherited) logo.
    saveSection($this, $this->display, DisplaySettingSections::BRANDING, [
        'font_family' => 'Lato',
        'remove_logo' => '1',
    ])->assertRedirect();

    // The profile's file must survive — other displays still show it.
    Storage::disk('public')->assertExists($profilePath);
    expect(ProfileSettings::get($this->profile->fresh(), 'logo'))->toBe($profilePath);
});

test('a display uploading its own logo overrides the profile and detaches branding', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->put(route('profiles.update', $this->profile), [
        'name' => $this->profile->name,
        'logo' => UploadedFile::fake()->image('profile-logo.png'),
    ])->assertRedirect();

    $profilePath = ProfileSettings::get($this->profile->fresh(), 'logo');

    saveSection($this, $this->display, DisplaySettingSections::BRANDING, [
        'font_family' => 'Roboto',
        'logo' => UploadedFile::fake()->image('own-logo.png'),
    ])->assertRedirect();

    $fresh = $this->display->fresh()->load('settings');
    $ownPath = DisplaySettings::getOwnSetting($fresh, 'logo');

    expect($ownPath)->toStartWith('displays/logos/')
        ->and($ownPath)->not->toBe($profilePath)
        ->and(DisplaySettings::sectionFollowsProfile($fresh, DisplaySettingSections::BRANDING))->toBeFalse();

    // Both files exist: the display's own and the profile's.
    Storage::disk('public')->assertExists($ownPath);
    Storage::disk('public')->assertExists($profilePath);
});

test('resetting branding drops the display logo and falls back to the profile image', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->put(route('profiles.update', $this->profile), [
        'name' => $this->profile->name,
        'logo' => UploadedFile::fake()->image('profile-logo.png'),
    ])->assertRedirect();
    $profilePath = ProfileSettings::get($this->profile->fresh(), 'logo');

    saveSection($this, $this->display, DisplaySettingSections::BRANDING, [
        'font_family' => 'Roboto',
        'logo' => UploadedFile::fake()->image('own-logo.png'),
    ]);

    $this->actingAs($this->user)
        ->post(route('displays.section.reset', ['display' => $this->display, 'section' => DisplaySettingSections::BRANDING]))
        ->assertRedirect();

    expect(DisplaySettings::getSetting($this->display->fresh(), 'logo'))->toBe($profilePath);
});

test('the profile form exposes the image fields', function () {
    $html = $this->actingAs($this->user)
        ->get(route('profiles.edit', $this->profile))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="logo"')
        ->toContain('name="background_image"')
        ->toContain('name="default_background"')
        ->toContain('enctype="multipart/form-data"');
});
