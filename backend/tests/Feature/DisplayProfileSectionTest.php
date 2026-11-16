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
use Inertia\Testing\AssertableInertia as Assert;

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
        ->assertRedirect(route('displays.index'));

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
        ->assertRedirect(route('displays.index'));

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

test('the displays page shows the linked profile and flags a customised display', function () {
    $this->actingAs($this->user)
        ->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('displays.0.profile.name', 'Meeting rooms')
            ->where('displays.0.profile.deviates', false));

    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    $this->actingAs($this->user)
        ->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('displays.0.profile.name', 'Meeting rooms')
            ->where('displays.0.profile.deviates', true));
});

test('the bulk how-to shows until a profile has actually been assigned', function () {
    // Bulk controls only appear with more than one display.
    Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'status' => DisplayStatus::ACTIVE,
        'display_profile_id' => null,
    ]);

    // The fixture display already follows a profile, so the how-to has served its purpose. The
    // bulk controls stay: the how-to is onboarding, the bulk bar is the feature.
    $this->actingAs($this->user)
        ->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('showBulkHint', false)
            ->where('canBulkAssign', true)
            ->where('profiles.0.name', 'Meeting rooms')
            ->where('urls.bulk', route('displays.profile.bulk')));

    // With nothing assigned anywhere, it comes back.
    Display::where('workspace_id', $this->workspace->id)->update(['display_profile_id' => null]);

    $this->actingAs($this->user)
        ->get(route('displays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('showBulkHint', true)->where('canBulkAssign', true));
});

test('the configuration screen shows a badge per section', function () {
    saveSection($this, $this->display, DisplaySettingSections::BRANDING, ['font_family' => 'Lato']);

    // Branding has its own values now, the others still follow the profile. Advertisement is
    // feature-gated, so it is not offered at all.
    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Configure/Index')
            ->where('sections.behavior.follows', true)
            ->where('sections.display.follows', true)
            ->where('sections.texts.follows', true)
            ->where('sections.branding.follows', false)
            ->where('sections.branding.reset', route('displays.section.reset', ['display' => $this->display, 'section' => 'branding']))
            ->where('sections.branding.update', route('displays.section.update', ['display' => $this->display, 'section' => 'branding']))
            ->missing('sections.advertisement')
            // Values resolve through the profile where the display has none of its own.
            ->where('values.booking_enabled', true)
            ->where('values.text_available', 'Vrij')
            ->where('values.font_family', 'Lato'));
});

test('the advertisement section is offered to accounts with the feature', function () {
    $this->user->featureFlags()->updateOrCreate([], ['advertisement' => true]);

    $this->actingAs($this->user->fresh())
        ->get(route('displays.configure', $this->display))
        ->assertInertia(fn (Assert $page) => $page->has('sections.advertisement'));
});

test('an inherited logo is shown as the profile\'s on the configuration screen', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->put(route('profiles.update', $this->profile), [
        'name' => $this->profile->name,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    $this->actingAs($this->user)
        ->get(route('displays.configure', $this->display))
        ->assertInertia(fn (Assert $page) => $page
            ->where('images.logo.own', false)
            ->where('images.logo.url', fn ($url) => str_starts_with($url, route('displays.images', ['display' => $this->display, 'type' => 'logo']))));
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
    $this->actingAs($this->user)
        ->get(route('profiles.edit', $this->profile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profiles/Edit')
            ->has('images.logo')
            ->has('images.background')
            ->has('options.backgrounds', 8)
            ->where('values.booking_enabled', true)
            ->where('values.text_available', 'Vrij'));
});
