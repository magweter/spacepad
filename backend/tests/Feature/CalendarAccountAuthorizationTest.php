<?php

use App\Enums\WorkspaceRole;
use App\Models\CalDAVAccount;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Before these policies existed, all three delete() actions took a route-bound model
 * and called ->delete() with no ownership check at all, so any authenticated user
 * could remove another tenant's calendar account by guessing/knowing its ULID.
 */
test('a user cannot delete an outlook account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->delete(route('outlook-accounts.delete', $account))
        ->assertForbidden();

    expect(OutlookAccount::find($account->id))->not->toBeNull();
});

test('a user cannot delete a google account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = GoogleAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->delete(route('google-accounts.delete', $account))
        ->assertForbidden();

    expect(GoogleAccount::find($account->id))->not->toBeNull();
});

test('a user cannot delete a caldav account from another workspace', function () {
    $owner = User::factory()->active()->create();
    $account = CalDAVAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $owner->primaryWorkspace()->id,
    ]);

    $attacker = User::factory()->active()->create();

    $this->actingAs($attacker)
        ->delete(route('caldav-accounts.delete', $account))
        ->assertForbidden();

    expect(CalDAVAccount::find($account->id))->not->toBeNull();
});

test('a member can delete a calendar account in their own workspace', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    // Connected by the owner, but the workspace is shared, so a member may manage it.
    $account = CalDAVAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($member)
        ->delete(route('caldav-accounts.delete', $account))
        ->assertRedirect();

    expect(CalDAVAccount::find($account->id))->toBeNull();
});

test('a legacy account without a workspace stays accessible to its creator only', function () {
    $creator = User::factory()->active()->create();
    $account = CalDAVAccount::factory()->create([
        'user_id' => $creator->id,
        'workspace_id' => null,
    ]);

    $other = User::factory()->active()->create();

    $this->actingAs($other)
        ->delete(route('caldav-accounts.delete', $account))
        ->assertForbidden();

    $this->actingAs($creator)
        ->delete(route('caldav-accounts.delete', $account))
        ->assertRedirect();

    expect(CalDAVAccount::find($account->id))->toBeNull();
});
