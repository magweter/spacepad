<?php

use App\Enums\WorkspaceRole;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The calendar and room pickers used to resolve accounts with
 * auth()->user()->outlookAccounts()->findOrFail($id), while the display-creation form
 * offered every account in the *workspace*. A colleague could therefore pick an account
 * from the dropdown and get a 404 on the next step — the core "co-manage devices" flow.
 *
 * These tests assert the authorization boundary only. They do not reach the external
 * provider: fetching happens after the authorize() call, and a failure there renders the
 * picker with an error message rather than a 403/404.
 */

/**
 * @return array{0: User, 1: User, 2: Workspace}
 */
function workspaceWithOwnerAndMember(): array
{
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    return [$owner, $member, $workspace];
}

test('a member is not denied access to a colleagues outlook account', function () {
    [$owner, $member, $workspace] = workspaceWithOwnerAndMember();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($member)->get("/calendars/outlook/{$account->id}");

    // Previously this was a 404 because the account was not the member's own.
    $response->assertOk();
});

test('a member is not denied access to a colleagues outlook rooms', function () {
    [$owner, $member, $workspace] = workspaceWithOwnerAndMember();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($member)
        ->get("/rooms/outlook/{$account->id}")
        ->assertOk();
});

test('an outsider cannot reach an accounts calendars', function () {
    [$owner, , $workspace] = workspaceWithOwnerAndMember();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->get("/calendars/outlook/{$account->id}")
        ->assertForbidden();
});

test('an outsider cannot reach an accounts rooms', function () {
    [$owner, , $workspace] = workspaceWithOwnerAndMember();

    $account = GoogleAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->get("/rooms/google/{$account->id}")
        ->assertForbidden();
});

test('an unknown account id is a 404 rather than a 403', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)
        ->get('/calendars/outlook/01JZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertNotFound();
});
