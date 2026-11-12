<?php

use App\Enums\DisplayStatus;
use App\Enums\UsageType;
use App\Helpers\DisplaySettings;
use App\Helpers\ProfileSettings;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->unlimited()->create();
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);
});

test('the profiles page lists the workspace profiles and selects the first', function () {
    $first = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'A rooms']);
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'B rooms']);
    Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $first->id, 'name' => 'Boardroom']);
    // Another workspace's profile never shows.
    DisplayProfile::factory()->create(['workspace_id' => User::factory()->active()->unlimited()->create()->primaryWorkspace()->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profiles/Index')
            ->where('hasPro', true)
            ->where('shell.nav.4.active', true)
            ->has('profiles', 2)
            ->where('profiles.0.name', 'A rooms')
            ->where('profiles.0.displays', 1)
            ->where('selected.name', 'A rooms')
            ->where('selected.displays.0.name', 'Boardroom')
            ->where('selected.displays.0.ownSections', [])
            ->where('selected.editUrl', route('profiles.edit', $first)));
});

test('a profile is selected from the query string', function () {
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'A rooms']);
    $second = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'B rooms']);
    ProfileSettings::set($second, 'booking_enabled', true, 'boolean');
    ProfileSettings::set($second, 'check_in_minutes', 10, 'integer');

    $this->actingAs($this->user)
        ->get(route('profiles.index', ['profile' => $second->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.name', 'B rooms')
            ->where('selected.values.booking_enabled', true)
            ->where('selected.values.check_in_minutes', 10)
            // Keys the profile does not hold get the form's defaults.
            ->where('selected.values.show_meeting_title', true)
            ->where('profiles.1.selected', true));
});

test('the profiles tab lists the workspace profiles', function () {
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);

    $this->actingAs($this->user)
        ->get(route('portal.manage', ['tab' => 'profiles']))
        ->assertOk()
        ->assertSee('Ground floor');
});

test('non-pro users see the profiles page with an upgrade note but cannot create one', function () {
    // Business user without a license/subscription is non-Pro in both cloud and self-hosted modes.
    $free = User::factory()->active()->create([
        'usage_type' => UsageType::BUSINESS,
    ]);
    session()->put('selected_workspace_id', $free->primaryWorkspace()->id);
    DisplayProfile::factory()->create(['workspace_id' => $free->primaryWorkspace()->id]);

    $this->actingAs($free)
        ->get(route('profiles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profiles/Index')
            ->where('hasPro', false)
            ->has('profiles', 0)
            ->where('selected', null));

    $this->actingAs($free)->get(route('profiles.create'))->assertForbidden();
    $this->actingAs($free)->post(route('profiles.store'), ['name' => 'Nope'])->assertForbidden();
});

test('the profile editor gets the profile values, images and linked displays', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);
    ProfileSettings::set($profile, 'font_family', 'Lato');
    ProfileSettings::set($profile, 'background_image', 'default_3');
    $display = Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $profile->id]);
    DisplaySettings::setSetting($display, 'text_available', 'Eigen tekst');

    $this->actingAs($this->user)
        ->get(route('profiles.edit', $profile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profiles/Edit')
            ->where('profile.name', 'Ground floor')
            ->where('values.font_family', 'Lato')
            ->where('images.background.default', 'default_3')
            ->where('images.logo.url', null)
            ->where('displays.0.ownSections', ['State texts'])
            ->where('urls.save', route('profiles.update', $profile))
            ->has('options.backgrounds', 8));
});

test('the create page starts from the defaults', function () {
    $this->actingAs($this->user)
        ->get(route('profiles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profiles/Edit')
            ->where('profile', null)
            ->where('values.font_family', 'Inter')
            ->where('values.cancel_permission', 'all')
            ->where('urls.save', route('profiles.store')));
});

test('a non-pro user gets the same tab teaser for profiles as for boards', function () {
    $free = User::factory()->active()->create([
        'usage_type' => UsageType::BUSINESS,
    ]);
    session()->put('selected_workspace_id', $free->primaryWorkspace()->id);

    $response = $this->actingAs($free)->get(route('portal.manage'))->assertOk();

    // The tab stays visible and explains itself instead of quietly not being there.
    $response->assertSee('Profiles (Pro Feature)');
    $response->assertSee('Boards (Pro Feature)');
    $response->assertSee('id="tab-profiles"', false);

    // But it cannot be opened, and none of its content is rendered.
    $response->assertDontSee('id="tab-content-profiles"', false);
    $response->assertDontSee("switchTab('profiles')", false);
});

test('a pro user gets a working profiles tab instead of the teaser', function () {
    $response = $this->actingAs($this->user)->get(route('portal.manage'))->assertOk();

    $response->assertSee("switchTab('profiles')", false);
    $response->assertSee('id="tab-content-profiles"', false);
    $response->assertDontSee('Profiles (Pro Feature)');
});

test('user can create a profile with settings', function () {
    $response = $this->actingAs($this->user)->post(route('profiles.store'), [
        'name' => 'Meeting rooms NL',
        'booking_enabled' => '1',
        'font_family' => 'Roboto',
        'text_available' => 'Vrij',
    ]);

    $profile = DisplayProfile::where('name', 'Meeting rooms NL')->first();
    $response->assertRedirect(route('profiles.index', ['profile' => $profile->id]));
    $this->assertDatabaseHas('display_profiles', [
        'name' => 'Meeting rooms NL',
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $profile = DisplayProfile::where('name', 'Meeting rooms NL')->first();
    expect(ProfileSettings::get($profile, 'booking_enabled'))->toBeTrue()
        ->and(ProfileSettings::get($profile, 'font_family'))->toBe('Roboto')
        ->and(ProfileSettings::get($profile, 'text_available'))->toBe('Vrij');
});

test('the profile form no longer assigns displays', function () {
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    // Posting display_ids must be ignored: assigning happens from the displays overview, so a
    // profile can never silently take a display away from another profile.
    $this->actingAs($this->user)->post(route('profiles.store'), [
        'name' => 'Bulk profile',
        'display_ids' => [$display->id],
    ])->assertRedirect();

    expect($display->fresh()->display_profile_id)->toBeNull();
});

test('updating a profile changes its settings and leaves the links alone', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $linked = Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $profile->id]);

    $this->actingAs($this->user)->put(route('profiles.update', $profile), [
        'name' => 'Renamed',
        'booking_enabled' => '1',
    ])->assertRedirect(route('profiles.index', ['profile' => $profile->id]));

    expect($profile->fresh()->name)->toBe('Renamed')
        ->and(ProfileSettings::get($profile->fresh(), 'booking_enabled'))->toBeTrue()
        ->and($linked->fresh()->display_profile_id)->toBe($profile->id);
});

test('deleting a profile copies its settings onto the linked displays', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    ProfileSettings::set($profile, 'text_available', 'Vrij');
    ProfileSettings::set($profile, 'booking_enabled', true, 'boolean');

    $display = Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $profile->id]);
    // A value the display already overrides must survive untouched.
    DisplaySettings::setSetting($display, 'text_available', 'Eigen tekst');

    $this->actingAs($this->user)
        ->delete(route('profiles.destroy', $profile))
        ->assertRedirect(route('profiles.index'));

    $this->assertDatabaseMissing('display_profiles', ['id' => $profile->id]);

    $fresh = $display->fresh()->load('settings');
    expect($fresh->display_profile_id)->toBeNull()
        // Inherited value was copied down, so the room keeps behaving the same...
        ->and(DisplaySettings::getSetting($fresh, 'booking_enabled'))->toBeTrue()
        // ...and the display's own override won.
        ->and(DisplaySettings::getSetting($fresh, 'text_available'))->toBe('Eigen tekst');
});

test('a user cannot manage a profile in another workspace', function () {
    $other = User::factory()->active()->unlimited()->create();
    $otherProfile = DisplayProfile::factory()->create(['workspace_id' => $other->primaryWorkspace()->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.edit', $otherProfile))
        ->assertForbidden();
});

test('a display can be linked to a profile from the configuration screen', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $display = Display::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->put(route('displays.profile.update', $display), ['display_profile_id' => $profile->id])
        ->assertRedirect(route('displays.configure', $display));

    expect($display->fresh()->display_profile_id)->toBe($profile->id);
});

test('the configuration screen names the followed profile without offering to switch it', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => $profile->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Configure/Index')
            ->where('shell.nav.1.active', true)
            ->where('profile.name', 'Ground floor')
            ->where('profile.editUrl', route('profiles.edit', $profile))
            // Nothing deviates yet, so there is nothing to reset.
            ->where('profile.deviates', false)
            // Linking and unlinking belong to the Displays page, which can do it in bulk.
            ->missing('profiles'));
});

test('the reset action appears only once a section has its own values', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => $profile->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertInertia(fn (Assert $page) => $page->where('profile.deviates', false));

    DisplaySettings::setSetting($display, 'text_available', 'Override');

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertInertia(fn (Assert $page) => $page->where('profile.deviates', true)
            ->where('sections.texts.follows', false)
            ->where('values.text_available', 'Override'));
});

test('a display without a profile is told so instead of being offered a picker', function () {
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Configure/Index')
            ->where('profile', null)
            ->missing('profiles'));
});

test('resetting a display to its profile removes its own settings', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    ProfileSettings::set($profile, 'text_available', 'Vrij');
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => $profile->id,
    ]);
    DisplaySettings::setSetting($display, 'text_available', 'Override');

    expect(DisplaySettings::getSetting($display->fresh(), 'text_available'))->toBe('Override');

    $this->actingAs($this->user)
        ->post(route('displays.settings.reset-to-profile', $display))
        ->assertRedirect(route('displays.configure', $display));

    // Own setting gone → now inherits the profile value.
    expect(DisplaySettings::getSetting($display->fresh(), 'text_available'))->toBe('Vrij');
});
