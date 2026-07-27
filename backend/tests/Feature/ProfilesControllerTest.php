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

test('user can view the profiles index', function () {
    DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.index'))
        ->assertOk();
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

test('creating a profile links the selected displays', function () {
    $displays = Display::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $this->actingAs($this->user)->post(route('profiles.store'), [
        'name' => 'Bulk profile',
        'display_ids' => $displays->pluck('id')->toArray(),
    ])->assertRedirect();

    $profile = DisplayProfile::where('name', 'Bulk profile')->first();
    foreach ($displays as $display) {
        expect($display->fresh()->display_profile_id)->toBe($profile->id);
    }
});

test('updating a profile changes settings and re-syncs linked displays', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $displayA = Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $profile->id]);
    $displayB = Display::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)->put(route('profiles.update', $profile), [
        'name' => 'Renamed',
        'booking_enabled' => '1',
        'display_ids' => [$displayB->id], // link B, unlink A
    ])->assertRedirect(route('profiles.index'));

    expect($profile->fresh()->name)->toBe('Renamed')
        ->and(ProfileSettings::get($profile->fresh(), 'booking_enabled'))->toBeTrue()
        ->and($displayA->fresh()->display_profile_id)->toBeNull()
        ->and($displayB->fresh()->display_profile_id)->toBe($profile->id);
});

test('deleting a profile detaches its displays', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $display = Display::factory()->create(['workspace_id' => $this->workspace->id, 'display_profile_id' => $profile->id]);

    $this->actingAs($this->user)
        ->delete(route('profiles.destroy', $profile))
        ->assertRedirect(route('profiles.index'));

    $this->assertDatabaseMissing('display_profiles', ['id' => $profile->id]);
    expect($display->fresh()->display_profile_id)->toBeNull();
});

test('a user cannot manage a profile in another workspace', function () {
    $other = User::factory()->active()->create(['is_unlimited' => true]);
    $otherProfile = DisplayProfile::factory()->create(['workspace_id' => $other->primaryWorkspace()->id]);

    $this->actingAs($this->user)
        ->get(route('profiles.edit', $otherProfile))
        ->assertForbidden();
});

test('a display can be linked to a profile from the display settings page', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id]);
    $display = Display::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->put(route('displays.profile.update', $display), ['display_profile_id' => $profile->id])
        ->assertRedirect(route('displays.settings.index', $display));

    expect($display->fresh()->display_profile_id)->toBe($profile->id);
});

test('the display settings page renders with the profile link section', function () {
    $profile = DisplayProfile::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Ground floor']);
    $display = Display::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'display_profile_id' => $profile->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('displays.settings.index', $display))
        ->assertOk()
        ->assertSee('Ground floor')
        ->assertSee('Reset settings to profile');
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
        ->assertRedirect(route('displays.settings.index', $display));

    // Own setting gone → now inherits the profile value.
    expect(DisplaySettings::getSetting($display->fresh(), 'text_available'))->toBe('Vrij');
});
