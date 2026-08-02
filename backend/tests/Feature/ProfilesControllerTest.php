<?php

use App\Enums\DisplayStatus;
use App\Enums\UsageType;
use App\Helpers\DisplaySettings;
use App\Helpers\ProfileSettings;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->active()->create([
        'is_unlimited' => true, // Pro
    ]);
    $this->workspace = $this->user->primaryWorkspace();
    session()->put('selected_workspace_id', $this->workspace->id);
});

test('the profiles url redirects to the dashboard tab', function () {
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.index'))
        ->assertRedirect(route('dashboard', ['tab' => 'profiles']));
});

test('the profiles tab lists the workspace profiles', function () {
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);

    $this->actingAs($this->user)
        ->get(route('dashboard', ['tab' => 'profiles']))
        ->assertOk()
        ->assertSee('Ground floor');
});

test('non-pro users cannot access profiles', function () {
    // Business user without a license/subscription is non-Pro in both cloud and self-hosted modes.
    $free = User::factory()->active()->create([
        'is_unlimited' => false,
        'is_manually_billed' => false,
        'usage_type' => UsageType::BUSINESS,
    ]);
    session()->put('selected_workspace_id', $free->primaryWorkspace()->id);

    $this->actingAs($free)
        ->get(route('profiles.index'))
        ->assertForbidden();
});

test('user can create a profile with settings', function () {
    $response = $this->actingAs($this->user)->post(route('profiles.store'), [
        'name' => 'Meeting rooms NL',
        'booking_enabled' => '1',
        'font_family' => 'Roboto',
        'text_available' => 'Vrij',
    ]);

    $response->assertRedirect(route('profiles.index'));
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
    ])->assertRedirect(route('profiles.index'));

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
    $other = User::factory()->active()->create(['is_unlimited' => true]);
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

test('the configuration screen renders with the profile link section', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => $profile->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.configure', $display))
        ->assertOk()
        ->assertSee('Ground floor')
        ->assertSee('Reset all sections to profile');
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
