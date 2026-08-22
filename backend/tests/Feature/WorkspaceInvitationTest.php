<?php

use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

/**
 * A Pro workspace with the given actor role. Pro currently comes from the owner.
 */
function invitingWorkspace(WorkspaceRole $actorRole = WorkspaceRole::OWNER): array
{
    $owner = User::factory()->active()->unlimited()->create();
    $workspace = Workspace::factory()->create(['name' => 'Playup']);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    if ($actorRole === WorkspaceRole::OWNER) {
        $actor = $owner;
    } else {
        $actor = User::factory()->active()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor->id,
            'role' => $actorRole,
        ]);
    }

    return [$workspace, $actor, $owner];
}

function actAsIn(Workspace $workspace, User $user): void
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);
}

test('an owner can invite a colleague and the email is sent', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'trnka@playup.sk',
        'role' => 'member',
    ])->assertRedirect();

    $invitation = WorkspaceInvitation::firstWhere('email', 'trnka@playup.sk');

    expect($invitation)->not->toBeNull();
    expect($invitation->workspace_id)->toBe($workspace->id);
    expect($invitation->role)->toBe(WorkspaceRole::MEMBER);
    expect($invitation->invited_by_user_id)->toBe($owner->id);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('the stored token is not the token that was emailed', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'uhrik@playup.sk',
        'role' => 'member',
    ]);

    $invitation = WorkspaceInvitation::firstWhere('email', 'uhrik@playup.sk');

    $emailedToken = null;
    Notification::assertSentOnDemand(
        WorkspaceInvitationNotification::class,
        function ($notification) use (&$emailedToken) {
            $emailedToken = $notification->acceptUrl;

            return true;
        }
    );

    // The row must hold a hash, never the credential itself.
    expect($emailedToken)->not->toContain($invitation->token);
    expect($invitation->token)->toHaveLength(64);
});

test('re-inviting rotates the token so the old link dies', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), ['email' => 'a@playup.sk', 'role' => 'member']);
    $firstToken = WorkspaceInvitation::firstWhere('email', 'a@playup.sk')->token;

    $this->post(route('workspaces.invitations.store'), ['email' => 'a@playup.sk', 'role' => 'admin']);
    $invitation = WorkspaceInvitation::firstWhere('email', 'a@playup.sk');

    expect($invitation->token)->not->toBe($firstToken);
    expect($invitation->role)->toBe(WorkspaceRole::ADMIN);
    expect(WorkspaceInvitation::where('email', 'a@playup.sk')->count())->toBe(1);
});

test('inviting an existing member fails', function () {
    [$workspace, $owner] = invitingWorkspace();

    $existing = User::factory()->active()->create(['email' => 'member@playup.sk']);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $existing->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'member@playup.sk',
        'role' => 'member',
    ])->assertSessionHasErrors('email');

    expect(WorkspaceInvitation::count())->toBe(0);
});

test('inviting yourself fails', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => $owner->email,
        'role' => 'member',
    ])->assertSessionHasErrors('email');
});

test('inviting someone as owner is rejected', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'new@playup.sk',
        'role' => 'owner',
    ])->assertSessionHasErrors('role');

    expect(WorkspaceInvitation::count())->toBe(0);
});

test('a disallowed domain cannot be invited', function () {
    config()->set('settings.allowed_logins', ['playup.sk']);

    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'outsider@example.com',
        'role' => 'member',
    ])->assertSessionHasErrors('email');
});

test('an admin can invite but a member cannot', function () {
    [$workspace, $admin] = invitingWorkspace(WorkspaceRole::ADMIN);
    actAsIn($workspace, $admin);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'byadmin@playup.sk',
        'role' => 'member',
    ])->assertRedirect();

    expect(WorkspaceInvitation::where('email', 'byadmin@playup.sk')->exists())->toBeTrue();

    [$otherWorkspace, $member] = invitingWorkspace(WorkspaceRole::MEMBER);
    actAsIn($otherWorkspace, $member);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'bymember@playup.sk',
        'role' => 'member',
    ])->assertForbidden();

    expect(WorkspaceInvitation::where('email', 'bymember@playup.sk')->exists())->toBeFalse();
});

test('a non-pro workspace cannot invite', function () {
    $owner = User::factory()->active()->create([
        'usage_type' => UsageType::BUSINESS,
    ]);
    $workspace = $owner->primaryWorkspace();
    actAsIn($workspace, $owner);

    $this->post(route('workspaces.invitations.store'), [
        'email' => 'nope@playup.sk',
        'role' => 'member',
    ])->assertForbidden();
});

test('an invitation can be withdrawn', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'gone@playup.sk',
    ]);

    $this->delete(route('workspaces.invitations.destroy', $invitation))->assertRedirect();

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('resending issues a new token', function () {
    [$workspace, $owner] = invitingWorkspace();
    actAsIn($workspace, $owner);

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'again@playup.sk',
        'expires_at' => now()->addDay(),
    ]);
    $oldToken = $invitation->token;

    $this->post(route('workspaces.invitations.resend', $invitation))->assertRedirect();

    expect($invitation->fresh()->token)->not->toBe($oldToken);
    expect($invitation->fresh()->expires_at->isAfter(now()->addDays(6)))->toBeTrue();
    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('an outsider cannot withdraw an invitation', function () {
    [$workspace] = invitingWorkspace();

    $invitation = WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id]);

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->delete(route('workspaces.invitations.destroy', $invitation))
        ->assertForbidden();

    expect(WorkspaceInvitation::find($invitation->id))->not->toBeNull();
});
