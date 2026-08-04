<?php

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\Display;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The admin panel is cloud-only.
    config(['settings.is_self_hosted' => false]);

    $this->admin = User::factory()->active()->create(['is_admin' => true]);
});

/**
 * Two separately-created workspaces, as in the real customers this tool exists for.
 *
 * @return array{0: Workspace, 1: Workspace, 2: User, 3: User}
 */
function twoSeparateWorkspaces(): array
{
    $sourceOwner = User::factory()->active()->create(['email' => 'uhrik@playup.sk']);
    $source = Workspace::factory()->create(['name' => 'Uhrik Workspace']);
    WorkspaceMember::create([
        'workspace_id' => $source->id,
        'user_id' => $sourceOwner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $targetOwner = User::factory()->active()->create(['email' => 'trnka@playup.sk']);
    $target = Workspace::factory()->create(['name' => 'Playup']);
    WorkspaceMember::create([
        'workspace_id' => $target->id,
        'user_id' => $targetOwner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    return [$source, $target, $sourceOwner, $targetOwner];
}

test('a non-admin cannot reach the merge tool', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($user)->get(route('admin.merge.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.merge.preview'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.merge.store'), [])->assertForbidden();
});

test('the merge tool is unreachable while impersonating', function () {
    $this->actingAs($this->admin);
    session()->put('impersonating', true);

    $this->get(route('admin.merge.index'))->assertForbidden();
});

test('the preview changes nothing', function () {
    [$source, $target, $sourceOwner] = twoSeparateWorkspaces();

    $display = Display::factory()->create(['workspace_id' => $source->id, 'user_id' => $sourceOwner->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.preview'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'move_members' => '1',
        ])
        ->assertOk()
        ->assertViewIs('pages.admin.merge')
        ->assertSee('Uhrik Workspace');

    expect($display->fresh()->workspace_id)->toBe($source->id);
    expect(Workspace::find($source->id))->not->toBeNull();
    expect($target->fresh()->hasMember($sourceOwner))->toBeFalse();
});

test('a merge moves the data, maps members and deletes the empty source', function () {
    [$source, $target, $sourceOwner] = twoSeparateWorkspaces();

    $display = Display::factory()->create(['workspace_id' => $source->id, 'user_id' => $sourceOwner->id]);
    $board = Board::factory()->create(['workspace_id' => $source->id, 'user_id' => $sourceOwner->id]);
    $account = OutlookAccount::factory()->create(['workspace_id' => $source->id, 'user_id' => $sourceOwner->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'move_members' => '1',
            'delete_source' => '1',
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect(route('admin.merge.index'));

    expect($display->fresh()->workspace_id)->toBe($target->id);
    expect($board->fresh()->workspace_id)->toBe($target->id);
    expect($account->fresh()->workspace_id)->toBe($target->id);

    // A source owner becomes an admin, never an owner of the surviving workspace.
    expect($target->fresh()->getUserRole($sourceOwner))->toBe(WorkspaceRole::ADMIN);

    expect(Workspace::find($source->id))->toBeNull();
    expect(WorkspaceMember::where('workspace_id', $source->id)->count())->toBe(0);
});

test('the source name must be typed to confirm', function () {
    [$source, $target] = twoSeparateWorkspaces();

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'confirm_name' => 'not the name',
        ])
        ->assertSessionHasErrors('confirm_name');

    expect(Workspace::find($source->id))->not->toBeNull();
});

test('an existing role in the target is never downgraded', function () {
    [$source, $target, $sourceOwner] = twoSeparateWorkspaces();

    // Already an owner of the target; merging must not demote them to admin.
    WorkspaceMember::create([
        'workspace_id' => $target->id,
        'user_id' => $sourceOwner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'move_members' => '1',
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    expect($target->fresh()->getUserRole($sourceOwner))->toBe(WorkspaceRole::OWNER);
});

test('clashing names are suffixed when asked', function () {
    [$source, $target] = twoSeparateWorkspaces();

    Display::factory()->create(['workspace_id' => $target->id, 'name' => 'Boardroom']);
    $clashing = Display::factory()->create(['workspace_id' => $source->id, 'name' => 'Boardroom']);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'rename_collisions' => '1',
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    expect($clashing->fresh()->name)->toBe('Boardroom (Uhrik Workspace)');
});

test('duplicate calendar accounts are reported but never merged away', function () {
    [$source, $target] = twoSeparateWorkspaces();

    OutlookAccount::factory()->create(['workspace_id' => $target->id, 'email' => 'rooms@playup.sk']);
    $duplicate = OutlookAccount::factory()->create(['workspace_id' => $source->id, 'email' => 'rooms@playup.sk']);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.preview'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
        ])
        ->assertOk()
        ->assertSee('rooms@playup.sk');

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    // Both survive as separate connected accounts.
    expect($duplicate->fresh()->workspace_id)->toBe($target->id);
    expect(OutlookAccount::where('workspace_id', $target->id)->where('email', 'rooms@playup.sk')->count())->toBe(2);
});

test('subscriptions are left completely alone', function () {
    [$source, $target, $sourceOwner] = twoSeparateWorkspaces();

    // Standing in for a paying account: hasActiveSubscription() consults the owners.
    $sourceOwner->update(['is_unlimited' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'move_members' => '1',
            'delete_source' => '1',
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    // is_unlimited is not a subscription, so the source is empty and does go away — but the
    // owner's billing flags are untouched either way.
    expect($sourceOwner->fresh()->is_unlimited)->toBeTrue();
});

test('pending invitations for the source are discarded', function () {
    [$source, $target] = twoSeparateWorkspaces();

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $source->id,
        'email' => 'someone@playup.sk',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('a workspace cannot be merged into itself', function () {
    [$source] = twoSeparateWorkspaces();

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $source->id,
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertSessionHasErrors('target_workspace_id');
});

test('orphaned rows are only adopted when the option is set', function () {
    [$source, $target, $sourceOwner] = twoSeparateWorkspaces();

    $orphan = Display::factory()->create(['workspace_id' => null, 'user_id' => $sourceOwner->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.merge.store'), [
            'source_workspace_id' => $source->id,
            'target_workspace_id' => $target->id,
            'confirm_name' => 'Uhrik Workspace',
        ])
        ->assertRedirect();

    // Not adopted: the option was off, and it belongs to no workspace.
    expect($orphan->fresh()->workspace_id)->toBeNull();
});
