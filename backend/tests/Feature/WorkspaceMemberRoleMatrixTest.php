<?php

use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * The agreed role matrix:
 *
 *   OWNER   everything, including billing and managing members
 *   ADMIN   everything except billing and managing members; may invite and revoke invites
 *   MEMBER  manages content (displays, boards, profiles, calendar accounts); no workspace
 *           administration at all
 *
 * Content management deliberately reaches down to MEMBER — before this, update/delete on
 * displays, boards and profiles all required canBeManagedBy() (owner|admin), so a member
 * could not manage anything and sharing a workspace was pointless.
 */

/**
 * Build a Pro workspace with one user in the given role, plus the content to act on.
 */
function workspaceActingAs(WorkspaceRole $role): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    // Pro comes from the owner in the current (pre-billing-move) model.
    $owner->update(['is_unlimited' => true]);

    if ($role === WorkspaceRole::OWNER) {
        $actor = $owner;
    } else {
        $actor = User::factory()->active()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor->id,
            'role' => $role,
        ]);
    }

    $display = Display::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $profile = DisplayProfile::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $account = OutlookAccount::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    return compact('workspace', 'actor', 'owner', 'display', 'board', 'profile', 'account');
}

dataset('roles', [
    'owner' => [WorkspaceRole::OWNER],
    'admin' => [WorkspaceRole::ADMIN],
    'member' => [WorkspaceRole::MEMBER],
]);

test('every role can manage the workspace content', function (WorkspaceRole $role) {
    ['actor' => $actor, 'display' => $display, 'board' => $board,
        'profile' => $profile, 'account' => $account] = workspaceActingAs($role);

    expect(Gate::forUser($actor)->allows('update', $display))->toBeTrue();
    expect(Gate::forUser($actor)->allows('delete', $display))->toBeTrue();
    expect(Gate::forUser($actor)->allows('update', $board))->toBeTrue();
    expect(Gate::forUser($actor)->allows('delete', $board))->toBeTrue();
    expect(Gate::forUser($actor)->allows('update', $profile))->toBeTrue();
    expect(Gate::forUser($actor)->allows('delete', $profile))->toBeTrue();
    expect(Gate::forUser($actor)->allows('update', $account))->toBeTrue();
    expect(Gate::forUser($actor)->allows('delete', $account))->toBeTrue();
})->with('roles');

test('every role can view the workspace and its members', function (WorkspaceRole $role) {
    ['actor' => $actor, 'workspace' => $workspace] = workspaceActingAs($role);

    expect(Gate::forUser($actor)->allows('view', $workspace))->toBeTrue();
    expect(Gate::forUser($actor)->allows('viewMembers', $workspace))->toBeTrue();
    expect(Gate::forUser($actor)->allows('leave', $workspace))->toBeTrue();
})->with('roles');

test('only owners and admins can rename the workspace and invite', function () {
    foreach ([[WorkspaceRole::OWNER, true], [WorkspaceRole::ADMIN, true], [WorkspaceRole::MEMBER, false]] as [$role, $allowed]) {
        ['actor' => $actor, 'workspace' => $workspace] = workspaceActingAs($role);

        expect(Gate::forUser($actor)->allows('update', $workspace))->toBe($allowed);
        expect(Gate::forUser($actor)->allows('invite', $workspace))->toBe($allowed);
        expect(Gate::forUser($actor)->allows('revokeInvitation', $workspace))->toBe($allowed);
    }
});

test('only the owner can manage members and billing', function () {
    foreach ([[WorkspaceRole::OWNER, true], [WorkspaceRole::ADMIN, false], [WorkspaceRole::MEMBER, false]] as [$role, $allowed]) {
        ['actor' => $actor, 'workspace' => $workspace] = workspaceActingAs($role);

        expect(Gate::forUser($actor)->allows('updateMemberRole', $workspace))->toBe($allowed);
        expect(Gate::forUser($actor)->allows('removeMember', $workspace))->toBe($allowed);
        expect(Gate::forUser($actor)->allows('manageBilling', $workspace))->toBe($allowed);
    }
});

test('a non-member is denied everything', function () {
    ['workspace' => $workspace, 'display' => $display, 'board' => $board,
        'profile' => $profile, 'account' => $account] = workspaceActingAs(WorkspaceRole::OWNER);

    $outsider = User::factory()->active()->create();

    foreach (['view', 'viewMembers', 'update', 'invite', 'revokeInvitation',
        'updateMemberRole', 'removeMember', 'manageBilling', 'leave'] as $ability) {
        expect(Gate::forUser($outsider)->allows($ability, $workspace))
            ->toBeFalse("outsider should not be able to {$ability} the workspace");
    }

    foreach ([$display, $board, $profile, $account] as $model) {
        expect(Gate::forUser($outsider)->allows('update', $model))->toBeFalse();
        expect(Gate::forUser($outsider)->allows('delete', $model))->toBeFalse();
        expect(Gate::forUser($outsider)->allows('view', $model))->toBeFalse();
    }
});

test('inviting requires Pro even for the owner', function () {
    $owner = User::factory()->active()->create([
        'is_unlimited' => false,
        'is_manually_billed' => false,
        'usage_type' => UsageType::BUSINESS,
    ]);
    $workspace = $owner->primaryWorkspace();

    expect(Gate::forUser($owner)->allows('invite', $workspace))->toBeFalse();

    // Revoking stays available, so an expired subscription cannot trap pending invites.
    expect(Gate::forUser($owner)->allows('revokeInvitation', $workspace))->toBeTrue();
});

test('a member can update display settings through the controller', function () {
    ['actor' => $member, 'display' => $display] = workspaceActingAs(WorkspaceRole::MEMBER);

    // Reaching the configuration screen at all proves the policy opened up; before this
    // change authorize('update', $display) rejected members outright.
    $this->actingAs($member)
        ->get(route('displays.configure', $display))
        ->assertOk();
});
