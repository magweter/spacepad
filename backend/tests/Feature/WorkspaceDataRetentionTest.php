<?php

use App\Enums\WorkspaceRole;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Team data must outlive the colleague who happened to create it.
 *
 * Every one of these tables used to have user_id NOT NULL with onDelete('cascade'), and
 * both delete paths iterated $user->displays regardless of workspace. So one member
 * deleting their account took the whole team's displays, devices and calendar accounts
 * with it.
 */

/**
 * A shared workspace owned by $owner, with $member as a plain member, where the *member*
 * created all the data.
 */
function sharedWorkspaceWithMemberOwnedData(): array
{
    $owner = User::factory()->active()->create();
    $member = User::factory()->active()->create();

    $workspace = Workspace::factory()->create(['name' => 'Playup']);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    // All created by the member, but belonging to the shared workspace.
    $display = Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
    $device = Device::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
    $account = CalDAVAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);

    return compact('owner', 'member', 'workspace', 'display', 'device', 'account');
}

test('a member deleting their own account leaves the teams data intact', function () {
    ['member' => $member, 'workspace' => $workspace, 'display' => $display,
        'device' => $device, 'account' => $account] = sharedWorkspaceWithMemberOwnedData();

    $this->actingAs($member)
        ->delete(route('profile.destroy'), ['confirm_email' => $member->email])
        ->assertRedirect(route('login'));

    expect(User::find($member->id))->toBeNull();
    expect(Workspace::find($workspace->id))->not->toBeNull();

    // The data survives, with provenance released rather than cascaded away.
    foreach ([$display, $device, $account] as $model) {
        $fresh = $model->fresh();
        expect($fresh)->not->toBeNull();
        expect($fresh->workspace_id)->toBe($workspace->id);
        expect($fresh->user_id)->toBeNull();
    }
});

test('an admin deleting a member leaves the teams data intact', function () {
    ['member' => $member, 'workspace' => $workspace, 'display' => $display,
        'account' => $account] = sharedWorkspaceWithMemberOwnedData();

    $admin = User::factory()->active()->create(['is_admin' => true]);

    // The admin panel is cloud-only, and so is billing_changes: its migration skips
    // creation when self-hosted, which is the default in the test env.
    config(['settings.is_self_hosted' => false]);
    (require database_path('migrations/2026_07_05_000001_create_billing_changes_table.php'))->up();

    $this->actingAs($admin)
        ->delete(route('admin.users.delete', $member), ['confirm_email' => $member->email])
        ->assertRedirect(route('admin.index'));

    expect(User::find($member->id))->toBeNull();
    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect($display->fresh()?->user_id)->toBeNull();
    expect($account->fresh()?->user_id)->toBeNull();
});

test('the owner leaving transfers the workspace to the remaining admin', function () {
    $owner = User::factory()->active()->create();
    $admin = User::factory()->active()->create();
    $plain = User::factory()->active()->create();

    $workspace = Workspace::factory()->create();
    foreach ([[$owner, WorkspaceRole::OWNER], [$admin, WorkspaceRole::ADMIN], [$plain, WorkspaceRole::MEMBER]] as [$user, $role]) {
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    $display = Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['confirm_email' => $owner->email])
        ->assertRedirect(route('login'));

    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect($display->fresh()?->user_id)->toBeNull();

    // The admin is promoted in preference to the plain member.
    expect($workspace->fresh()->getUserRole($admin))->toBe(WorkspaceRole::OWNER);
    expect($workspace->fresh()->getUserRole($plain))->toBe(WorkspaceRole::MEMBER);
});

test('a workspace with no other members is purged with its owner', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    $display = Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => $user->email])
        ->assertRedirect(route('login'));

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(Display::find($display->id))->toBeNull();
});

test('data belonging to no workspace is cleaned up with the user', function () {
    $user = User::factory()->active()->create();

    // A row the session-based workspace stamping never labelled.
    $orphan = Display::factory()->create([
        'workspace_id' => null,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirm_email' => $user->email])
        ->assertRedirect(route('login'));

    expect(Display::find($orphan->id))->toBeNull();
});
