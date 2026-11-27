<?php

use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{0: Workspace, 1: array<string, User>}
 */
function settingsPageWorkspace(array $roles = ['owner', 'admin', 'member']): array
{
    $workspace = Workspace::factory()->create(['name' => 'Playup']);
    $users = [];

    foreach ($roles as $role) {
        $users[$role] = User::factory()->active()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $users[$role]->id, 'role' => WorkspaceRole::from($role)]);
    }

    return [$workspace, $users];
}

function settingsPageAs(Workspace $workspace, User $user)
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);

    return test()->get(route('settings.index'));
}

test('owners and admins can rename, members only see the name', function () {
    [$workspace, $users] = settingsPageWorkspace();

    foreach (['owner', 'admin'] as $role) {
        settingsPageAs($workspace, $users[$role])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings/Index')
                ->where('shell.nav.8.active', true)
                ->where('workspace.name', 'Playup')
                ->where('workspace.members', 3)
                ->where('can.rename', true)
                ->where('urls.update', route('workspaces.update', $workspace)));
    }

    settingsPageAs($workspace, $users['member'])
        ->assertInertia(fn (Assert $page) => $page->where('can.rename', false)->where('can.leave', true));
});

test('the only owner has to pick a successor before leaving', function () {
    [$workspace, $users] = settingsPageWorkspace();

    settingsPageAs($workspace, $users['owner'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('leave.needsSuccessor', true)
            ->has('leave.candidates', 2));

    settingsPageAs($workspace, $users['admin'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('leave.needsSuccessor', false)
            ->where('leave.candidates', []));
});

test('the only member cannot leave, and is told why the workspace cannot be deleted', function () {
    [$workspace, $users] = settingsPageWorkspace(['owner']);
    Display::factory()->create(['workspace_id' => $workspace->id]);

    settingsPageAs($workspace, $users['owner'])
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.leave', false)
            ->where('can.delete', false)
            ->whereNot('deleteBlocked', null));
});

test('renaming from the settings page lands back on it', function () {
    [$workspace, $users] = settingsPageWorkspace();
    $this->actingAs($users['admin']);
    session()->put('selected_workspace_id', $workspace->id);

    $this->from(route('settings.index'))
        ->patch(route('workspaces.update', $workspace), ['name' => 'Playup HQ'])
        ->assertRedirect(route('settings.index'));

    expect($workspace->fresh()->name)->toBe('Playup HQ');
});
