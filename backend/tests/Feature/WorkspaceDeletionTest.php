<?php

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Self-service deletion of a leftover empty workspace.
 *
 * Narrow on purpose: it must be empty, solely inhabited by the owner, unbilled, and the
 * owner must still have somewhere else to work.
 */

/**
 * A user who owns an empty workspace and is also an owner elsewhere.
 *
 * @return array{0: User, 1: Workspace}
 */
function ownerOfSpareEmptyWorkspace(): array
{
    $user = User::factory()->active()->create();

    // The factory's active() state connects an Outlook account to the primary workspace, so
    // use a second, genuinely empty workspace as the deletion candidate.
    $spare = Workspace::factory()->create(['name' => 'Leftover']);
    WorkspaceMember::create([
        'workspace_id' => $spare->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    return [$user, $spare];
}

function actingInWorkspace(User $user, Workspace $workspace): void
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);
}

test('an empty spare workspace can be deleted by its owner', function () {
    [$user, $spare] = ownerOfSpareEmptyWorkspace();
    actingInWorkspace($user, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'Leftover'])
        ->assertRedirect(route('dashboard'));

    expect(Workspace::find($spare->id))->toBeNull();
    expect(WorkspaceMember::where('workspace_id', $spare->id)->count())->toBe(0);
    expect(session('selected_workspace_id'))->toBeNull();
});

test('the name must be typed correctly', function () {
    [$user, $spare] = ownerOfSpareEmptyWorkspace();
    actingInWorkspace($user, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'wrong'])
        ->assertSessionHasErrors('confirm_name');

    expect(Workspace::find($spare->id))->not->toBeNull();
});

test('any single piece of data blocks deletion', function (string $model) {
    [$user, $spare] = ownerOfSpareEmptyWorkspace();

    $model::factory()->create(['workspace_id' => $spare->id]);

    actingInWorkspace($user, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'Leftover'])
        ->assertForbidden();

    expect(Workspace::find($spare->id))->not->toBeNull();
})->with([
    'a display' => [Display::class],
    'a device' => [Device::class],
    'a board' => [Board::class],
    'a display profile' => [DisplayProfile::class],
    'a caldav account' => [CalDAVAccount::class],
]);

test('a workspace with another member cannot be deleted', function () {
    [$user, $spare] = ownerOfSpareEmptyWorkspace();

    $colleague = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $spare->id,
        'user_id' => $colleague->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    actingInWorkspace($user, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'Leftover'])
        ->assertForbidden();

    expect(Workspace::find($spare->id))->not->toBeNull();
});

test('your only workspace cannot be deleted', function () {
    $user = User::factory()->active()->create();
    $only = $user->primaryWorkspace();

    // Remove the factory's account so the workspace is genuinely empty; it is still the
    // user's only workspace, so deletion must stay blocked.
    $only->outlookAccounts()->delete();

    actingInWorkspace($user, $only);

    $this->delete(route('workspaces.destroy', $only), ['confirm_name' => $only->name])
        ->assertForbidden();

    expect(Workspace::find($only->id))->not->toBeNull();
});

test('being only a member elsewhere is not enough', function () {
    $user = User::factory()->active()->create();
    $spare = Workspace::factory()->create(['name' => 'Leftover']);
    WorkspaceMember::create([
        'workspace_id' => $spare->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    // Strip the primary workspace back so 'Leftover' is the only owned+empty one, and make
    // the user a plain member of a third workspace.
    $user->primaryWorkspace()->outlookAccounts()->delete();
    WorkspaceMember::where('user_id', $user->id)
        ->where('workspace_id', '!=', $spare->id)
        ->delete();

    $elsewhere = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $elsewhere->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    actingInWorkspace($user, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'Leftover'])
        ->assertForbidden();

    expect(Workspace::find($spare->id))->not->toBeNull();
});

test('a non-owner cannot delete an empty workspace', function () {
    $owner = User::factory()->active()->create();
    $spare = Workspace::factory()->create(['name' => 'Leftover']);
    WorkspaceMember::create([
        'workspace_id' => $spare->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $admin = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $spare->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::ADMIN,
    ]);

    actingInWorkspace($admin, $spare);

    $this->delete(route('workspaces.destroy', $spare), ['confirm_name' => 'Leftover'])
        ->assertForbidden();

    expect(Workspace::find($spare->id))->not->toBeNull();
});

test('the delete button only appears when deletion is actually possible', function () {
    [$user, $spare] = ownerOfSpareEmptyWorkspace();

    actingInWorkspace($user, $spare);
    $this->get(route('workspaces.members'))->assertSee('Delete workspace');

    // Add a display: the option disappears.
    Display::factory()->create(['workspace_id' => $spare->id]);
    $this->get(route('workspaces.members'))->assertDontSee('Delete workspace');
});
