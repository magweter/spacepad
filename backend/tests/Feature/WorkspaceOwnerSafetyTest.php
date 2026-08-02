<?php

use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A workspace must always keep at least one owner, and removing a member must never
 * remove their work.
 */

/**
 * @return array{0: Workspace, 1: array<string, User>, 2: array<string, WorkspaceMember>}
 */
function teamWithRoles(array $roles = ['owner', 'admin', 'member']): array
{
    $workspace = Workspace::factory()->create(['name' => 'Playup']);
    $users = [];
    $memberships = [];

    foreach ($roles as $key) {
        $user = User::factory()->active()->create(['is_unlimited' => $key === 'owner']);
        $users[$key] = $user;
        $memberships[$key] = WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::from($key),
        ]);
    }

    return [$workspace, $users, $memberships];
}

/**
 * Act as the given user with this workspace selected. Named for its most common use; it
 * works for any member.
 */
function asOwnerOf(Workspace $workspace, User $owner): void
{
    test()->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);
}

test('the last owner cannot be demoted', function () {
    [$workspace, $users, $memberships] = teamWithRoles(['owner', 'member']);
    asOwnerOf($workspace, $users['owner']);

    // Another owner tries it, so the guard is exercised rather than the self-role rule.
    $secondOwner = User::factory()->active()->create();
    $secondMembership = WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $secondOwner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    // Two owners now, so demoting one is allowed.
    $this->patch(route('workspaces.members.update', $secondMembership), ['role' => 'member'])
        ->assertRedirect();
    expect($secondMembership->fresh()->role)->toBe(WorkspaceRole::MEMBER);

    // Back to one owner: the member cannot now demote the owner either, and the owner
    // cannot demote themselves.
    $this->patch(route('workspaces.members.update', $memberships['owner']), ['role' => 'member'])
        ->assertRedirect();

    expect($memberships['owner']->fresh()->role)->toBe(WorkspaceRole::OWNER);
    expect($workspace->fresh()->owners()->count())->toBe(1);
});

test('you cannot change your own role', function () {
    [$workspace, $users, $memberships] = teamWithRoles(['owner', 'member']);
    asOwnerOf($workspace, $users['owner']);

    $this->patch(route('workspaces.members.update', $memberships['owner']), ['role' => 'member'])
        ->assertSessionHas('error');

    expect($memberships['owner']->fresh()->role)->toBe(WorkspaceRole::OWNER);
});

test('you cannot remove yourself through the members list', function () {
    [$workspace, $users, $memberships] = teamWithRoles(['owner', 'member']);
    asOwnerOf($workspace, $users['owner']);

    $this->delete(route('workspaces.members.destroy', $memberships['owner']))
        ->assertSessionHas('error');

    expect(WorkspaceMember::find($memberships['owner']->id))->not->toBeNull();
});

test('removing a member leaves their displays in the workspace', function () {
    [$workspace, $users, $memberships] = teamWithRoles(['owner', 'member']);
    asOwnerOf($workspace, $users['owner']);

    $display = Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $users['member']->id,
    ]);

    $this->delete(route('workspaces.members.destroy', $memberships['member']))
        ->assertRedirect();

    expect(WorkspaceMember::find($memberships['member']->id))->toBeNull();
    expect(User::find($users['member']->id))->not->toBeNull();

    // The display stays, and keeps its provenance since the user still exists.
    expect($display->fresh()->workspace_id)->toBe($workspace->id);
});

test('an admin cannot remove members', function () {
    [$workspace, $users, $memberships] = teamWithRoles(['owner', 'admin', 'member']);

    $this->actingAs($users['admin']);
    session()->put('selected_workspace_id', $workspace->id);

    $this->delete(route('workspaces.members.destroy', $memberships['member']))
        ->assertForbidden();

    $this->patch(route('workspaces.members.update', $memberships['member']), ['role' => 'admin'])
        ->assertForbidden();

    expect(WorkspaceMember::find($memberships['member']->id))->not->toBeNull();
});

test('a member can leave', function () {
    [$workspace, $users] = teamWithRoles(['owner', 'member']);

    $this->actingAs($users['member']);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('workspaces.leave'))->assertRedirect(route('dashboard'));

    expect($workspace->fresh()->hasMember($users['member']))->toBeFalse();
    expect(session('selected_workspace_id'))->toBeNull();
});

test('the last owner must nominate a successor to leave', function () {
    [$workspace, $users] = teamWithRoles(['owner', 'member']);
    asOwnerOf($workspace, $users['owner']);

    // No successor given.
    $this->post(route('workspaces.leave'))->assertSessionHasErrors('successor_user_id');
    expect($workspace->fresh()->hasMember($users['owner']))->toBeTrue();

    // With a successor, the member is promoted and the owner leaves.
    $this->post(route('workspaces.leave'), ['successor_user_id' => $users['member']->id])
        ->assertRedirect(route('dashboard'));

    expect($workspace->fresh()->hasMember($users['owner']))->toBeFalse();
    expect($workspace->fresh()->getUserRole($users['member']))->toBe(WorkspaceRole::OWNER);
});

test('the sole member of a workspace cannot leave it', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    asOwnerOf($workspace, $user);

    $this->post(route('workspaces.leave'))->assertSessionHas('error');

    expect($workspace->fresh()->hasMember($user))->toBeTrue();
});

test('a workspace can be renamed by an admin but not a member', function () {
    [$workspace, $users] = teamWithRoles(['owner', 'admin', 'member']);

    $this->actingAs($users['admin']);
    session()->put('selected_workspace_id', $workspace->id);
    $this->patch(route('workspaces.update', $workspace), ['name' => 'PlayUp s.r.o.'])->assertRedirect();
    expect($workspace->fresh()->name)->toBe('PlayUp s.r.o.');

    $this->actingAs($users['member']);
    $this->patch(route('workspaces.update', $workspace), ['name' => 'Nope'])->assertForbidden();
    expect($workspace->fresh()->name)->toBe('PlayUp s.r.o.');
});

test('a user can create an additional workspace and becomes its owner', function () {
    $user = User::factory()->active()->create();
    $this->actingAs($user);

    $this->post(route('workspaces.store'), ['name' => 'Second Site'])->assertRedirect(route('dashboard'));

    $created = Workspace::firstWhere('name', 'Second Site');

    expect($created)->not->toBeNull();
    expect($created->getUserRole($user))->toBe(WorkspaceRole::OWNER);
    expect(session('selected_workspace_id'))->toBe($created->id);
});

test('the members page is reachable and lists the team', function () {
    [$workspace, $users] = teamWithRoles(['owner', 'admin', 'member']);
    asOwnerOf($workspace, $users['owner']);

    $this->get(route('workspaces.members'))
        ->assertOk()
        ->assertViewIs('pages.workspaces.members')
        ->assertSee($users['admin']->email)
        ->assertSee($users['member']->email);
});

test('only the owner gets subscription controls on the manage workspace page', function () {
    [$workspace, $users] = teamWithRoles(['owner', 'admin', 'member']);
    $workspace->update(['is_unlimited' => true]);

    // Everyone sees what the workspace is billed for...
    foreach (['owner', 'admin', 'member'] as $role) {
        asOwnerOf($workspace, $users[$role]);

        $this->get(route('workspaces.members'))
            ->assertOk()
            ->assertSee('Total billed to subscription');
    }

    // ...but only the owner can act on it. Before billing moved here, the button was on the
    // personal account page and shown to every member.
    asOwnerOf($workspace, $users['owner']);
    $this->get(route('workspaces.members'))->assertSee('Manage subscription');

    foreach (['admin', 'member'] as $role) {
        asOwnerOf($workspace, $users[$role]);

        $this->get(route('workspaces.members'))
            ->assertDontSee('Manage subscription')
            ->assertSee('Only its owner can change the subscription');
    }
});

test('an outsider cannot see the members page of a workspace they are not in', function () {
    [$workspace] = teamWithRoles(['owner']);

    $outsider = User::factory()->active()->create();
    $this->actingAs($outsider);
    // Force the session to point at someone else's workspace.
    session()->put('selected_workspace_id', $workspace->id);

    // getSelectedWorkspace() validates membership and falls back to their own workspace,
    // so they see their own team rather than a stranger's.
    $response = $this->get(route('workspaces.members'));

    $response->assertOk();
    $response->assertDontSee($workspace->name);
});
