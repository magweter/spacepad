<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DisplayStatus;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\OutlookAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resetAccount returns 422 when no calendar account is linked', function () {
    $user = User::factory()->active()->create();

    $calendar = Calendar::factory()->create(['user_id' => $user->id]);
    $display = Display::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'calendar_id' => $calendar->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $this->actingAs($user)
        ->postJson(route('displays.diagnostics.reset-account', $display))
        ->assertStatus(422)
        ->assertJson(['ok' => false]);
});

test('resetAccount sets the linked account status back to connected', function () {
    $user = User::factory()->active()->create();

    $account = OutlookAccount::factory()->create([
        'user_id' => $user->id,
        'status' => AccountStatus::ERROR,
    ]);
    $calendar = Calendar::factory()->create([
        'user_id' => $user->id,
        'outlook_account_id' => $account->id,
    ]);
    $display = Display::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'calendar_id' => $calendar->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    $this->actingAs($user)
        ->postJson(route('displays.diagnostics.reset-account', $display))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($account->refresh()->status)->toBe(AccountStatus::CONNECTED);
});

test('resetAccount restores a display parked in error back to active', function () {
    $user = User::factory()->active()->create();

    $account = OutlookAccount::factory()->create([
        'user_id' => $user->id,
        'status' => AccountStatus::ERROR,
    ]);
    $calendar = Calendar::factory()->create([
        'user_id' => $user->id,
        'outlook_account_id' => $account->id,
    ]);
    $display = Display::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'calendar_id' => $calendar->id,
        'status' => DisplayStatus::ERROR,
    ]);

    $this->actingAs($user)
        ->postJson(route('displays.diagnostics.reset-account', $display))
        ->assertOk();

    expect($display->refresh()->status)->toBe(DisplayStatus::ACTIVE);
    expect($account->refresh()->status)->toBe(AccountStatus::CONNECTED);
});

test('resetAccount is forbidden for a user who cannot manage the display', function () {
    $owner = User::factory()->active()->create();
    $stranger = User::factory()->active()->create();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'status' => AccountStatus::ERROR,
    ]);
    $calendar = Calendar::factory()->create([
        'user_id' => $owner->id,
        'outlook_account_id' => $account->id,
    ]);
    $display = Display::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
        'calendar_id' => $calendar->id,
        'status' => DisplayStatus::ERROR,
    ]);

    $this->actingAs($stranger)
        ->postJson(route('displays.diagnostics.reset-account', $display))
        ->assertForbidden();

    expect($account->refresh()->status)->toBe(AccountStatus::ERROR);
});
