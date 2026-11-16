<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceJoinRequest;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

/**
 * @return array{0: Workspace, 1: array<string, User>}
 */
function teamPageWorkspace(bool $pro = true): array
{
    $workspace = Workspace::factory()->create(['name' => 'Playup', 'is_unlimited' => $pro]);
    $users = [];

    foreach (['owner', 'admin', 'member'] as $role) {
        $users[$role] = User::factory()->active()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $users[$role]->id, 'role' => WorkspaceRole::from($role)]);
    }

    return [$workspace, $users];
}

function teamPageAs(Workspace $workspace, User $user)
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);

    return test()->get(route('workspaces.members'));
}

test('the owner sees the team, pending invitations and every action', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace();
    WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id, 'email' => 'new@playup.sk', 'invited_by_user_id' => $users['admin']->id]);
    WorkspaceInvitation::factory()->expired()->create(['workspace_id' => $workspace->id]);

    teamPageAs($workspace, $users['owner'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Team/Index')
            ->where('shell.nav.5.active', true)
            ->has('members', 3)
            ->has('invitations', 1)
            ->where('invitations.0.email', 'new@playup.sk')
            ->where('invitations.0.invitedBy', $users['admin']->name)
            ->where('can.invite', true)
            ->where('can.revokeInvitation', true)
            ->where('can.changeRoles', true)
            ->where('can.removeMembers', true)
            ->where('needsPro', false)
            ->where('invitationLifetimeDays', WorkspaceInvitation::LIFETIME_DAYS));
});

test('an admin invites but does not change roles', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace();

    teamPageAs($workspace, $users['admin'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.invite', true)
            ->where('can.changeRoles', false)
            ->where('can.removeMembers', false));
});

test('a member sees the team but no invitations', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace();
    WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id]);

    teamPageAs($workspace, $users['member'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 3)
            ->where('invitations', [])
            ->where('can.invite', false)
            ->where('can.revokeInvitation', false)
            ->where('can.changeRoles', false));
});

test('without Pro the page says inviting is part of Pro, and points owners at billing', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace(pro: false);

    teamPageAs($workspace, $users['owner'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('needsPro', true)
            ->where('can.invite', false)
            ->where('can.manageBilling', true)
            ->where('urls.billing', route('billing.index')));
});

test('pending join requests are listed for whoever can let them in', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace();
    $asker = User::factory()->active()->create(['name' => 'Tom Bakker']);
    $request = WorkspaceJoinRequest::create(['workspace_id' => $workspace->id, 'user_id' => $asker->id, 'status' => WorkspaceJoinRequest::PENDING]);
    WorkspaceJoinRequest::create(['workspace_id' => $workspace->id, 'user_id' => User::factory()->active()->create()->id, 'status' => WorkspaceJoinRequest::DECLINED]);

    teamPageAs($workspace, $users['admin'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('joinRequests', 1)
            ->where('joinRequests.0.name', 'Tom Bakker')
            ->where('joinRequests.0.url', route('join-requests.show', $request)));

    teamPageAs($workspace, $users['member'])
        ->assertInertia(fn (Assert $page) => $page->where('joinRequests', []));
});

test('changing a role from the team page lands back on it', function () {
    config(['settings.is_self_hosted' => false]);
    [$workspace, $users] = teamPageWorkspace();
    $membership = WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $users['member']->id)->first();

    $this->actingAs($users['owner']);
    session()->put('selected_workspace_id', $workspace->id);

    $this->from(route('workspaces.members'))
        ->patch(route('workspaces.members.update', $membership), ['role' => 'admin'])
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('success');

    expect($membership->fresh()->role)->toBe(WorkspaceRole::ADMIN);
});
