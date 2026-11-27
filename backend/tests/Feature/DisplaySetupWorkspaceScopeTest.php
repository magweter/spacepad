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
 * The display setup lists an account's rooms and calendars for everyone in the workspace,
 * not only for the colleague who connected it: co-managing devices is the core flow. Anyone
 * outside the workspace is refused.
 *
 * These tests assert the authorization boundary only. They do not reach the external
 * provider: fetching happens after the authorize() call, and a failure there still answers
 * with an empty list rather than a 403/404.
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

test('a member can list the rooms and calendars of a colleagues account', function () {
    [$owner, $member, $workspace] = workspaceWithOwnerAndMember();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $response = $this->actingAs($member)->getJson(route('setup.first-display.resources', ['provider' => 'outlook', 'account' => $account->id]));

    // Previously this was a 404 because the account was not the member's own.
    $response->assertOk();
});

test('an outsider cannot list a microsoft accounts rooms and calendars', function () {
    [$owner, , $workspace] = workspaceWithOwnerAndMember();

    $account = OutlookAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->getJson(route('setup.first-display.resources', ['provider' => 'outlook', 'account' => $account->id]))
        ->assertForbidden();
});

test('an outsider cannot list a google accounts rooms and calendars', function () {
    [$owner, , $workspace] = workspaceWithOwnerAndMember();

    $account = GoogleAccount::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->getJson(route('setup.first-display.resources', ['provider' => 'google', 'account' => $account->id]))
        ->assertForbidden();
});

test('an unknown account id is a 404 rather than a 403', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)
        ->getJson(route('setup.first-display.resources', ['provider' => 'outlook', 'account' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']))
        ->assertNotFound();
});
