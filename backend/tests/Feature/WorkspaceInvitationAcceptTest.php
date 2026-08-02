<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Create a pending invitation and return it with its plaintext token.
 *
 * @return array{0: WorkspaceInvitation, 1: string, 2: Workspace, 3: User}
 */
function pendingInvitation(string $email = 'trnka@playup.sk', WorkspaceRole $role = WorkspaceRole::MEMBER): array
{
    $owner = User::factory()->active()->create(['is_unlimited' => true]);
    $workspace = Workspace::factory()->create(['name' => 'Playup']);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $plainToken = Str::random(64);

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'invited_by_user_id' => $owner->id,
        'email' => $email,
        'role' => $role,
        'token' => WorkspaceInvitation::hashToken($plainToken),
        'expires_at' => now()->addDays(7),
    ]);

    return [$invitation, $plainToken, $workspace, $owner];
}

test('a guest with no account joins and gets no personal workspace', function () {
    [$invitation, $token, $workspace] = pendingInvitation();

    $workspacesBefore = Workspace::count();

    $this->get(route('invitations.show', $token))->assertOk();

    $this->post(route('invitations.accept', $token))
        ->assertRedirect(route('dashboard'));

    $user = User::firstWhere('email', 'trnka@playup.sk');

    expect($user)->not->toBeNull();
    expect($workspace->fresh()->hasMember($user))->toBeTrue();
    expect($workspace->fresh()->getUserRole($user))->toBe(WorkspaceRole::MEMBER);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($invitation->fresh()->accepted_by_user_id)->toBe($user->id);

    // The regression that matters: no empty "trnka's Workspace" alongside the real one.
    expect(Workspace::count())->toBe($workspacesBefore);

    $this->assertAuthenticatedAs($user);
    expect(session('selected_workspace_id'))->toBe($workspace->id);
});

test('an invited newcomer lands on the dashboard rather than onboarding', function () {
    [, $token] = pendingInvitation();

    $this->post(route('invitations.accept', $token));

    // CheckUserActive redirects to /onboarding when the user is not onboarded.
    $this->get(route('dashboard'))->assertOk();
});

test('a guest with an existing account joins without creating a user', function () {
    [$invitation, $token, $workspace] = pendingInvitation('existing@playup.sk');

    $existing = User::factory()->active()->create(['email' => 'existing@playup.sk']);
    $usersBefore = User::count();

    $this->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));

    expect(User::count())->toBe($usersBefore);
    expect($workspace->fresh()->hasMember($existing))->toBeTrue();
    $this->assertAuthenticatedAs($existing->fresh());
});

test('being signed in as a different user shows the mismatch screen and changes nothing', function () {
    [$invitation, $token, $workspace] = pendingInvitation();

    $someoneElse = User::factory()->active()->create(['email' => 'other@example.com']);

    $this->actingAs($someoneElse)
        ->get(route('invitations.show', $token))
        ->assertOk()
        ->assertViewIs('pages.invitations.mismatch');

    // And the POST is refused outright, not silently accepted into the wrong account.
    $this->actingAs($someoneElse)
        ->post(route('invitations.accept', $token))
        ->assertForbidden();

    expect($workspace->fresh()->hasMember($someoneElse))->toBeFalse();
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('accepting twice is harmless', function () {
    [$invitation, $token, $workspace] = pendingInvitation();

    $this->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));

    $user = User::firstWhere('email', 'trnka@playup.sk');

    // Second attempt: the invitation is used up, so the link no longer accepts.
    $this->post(route('invitations.accept', $token))->assertStatus(410);

    expect(WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $user->id)->count())->toBe(1);
});

test('an already accepted invitation sends an existing member to the dashboard', function () {
    [$invitation, $token, $workspace] = pendingInvitation();

    $this->post(route('invitations.accept', $token));
    $user = User::firstWhere('email', 'trnka@playup.sk');

    $this->actingAs($user)
        ->get(route('invitations.show', $token))
        ->assertRedirect(route('dashboard'));
});

test('an expired invitation cannot be used', function () {
    [$invitation, $token] = pendingInvitation();
    $invitation->update(['expires_at' => now()->subDay()]);

    $this->get(route('invitations.show', $token))
        ->assertStatus(410)
        ->assertViewIs('pages.invitations.expired');

    $this->post(route('invitations.accept', $token))->assertStatus(410);

    expect(User::whereRaw('lower(email) = ?', ['trnka@playup.sk'])->exists())->toBeFalse();
});

test('an unknown token is a 404', function () {
    $this->get(route('invitations.show', Str::random(64)))
        ->assertNotFound()
        ->assertViewIs('pages.invitations.invalid');

    $this->post(route('invitations.accept', Str::random(64)))->assertNotFound();
});

test('a withdrawn invitation cannot be accepted', function () {
    [$invitation, $token] = pendingInvitation();
    $invitation->delete();

    $this->post(route('invitations.accept', $token))->assertNotFound();
});

test('an invitation for a disallowed address is blocked at accept time', function () {
    [$invitation, $token] = pendingInvitation();

    // The allowlist changed after the invitation went out.
    config()->set('settings.allowed_logins', ['othercompany.com']);

    $this->get(route('invitations.show', $token))
        ->assertStatus(403)
        ->assertViewIs('pages.invitations.blocked');

    $this->post(route('invitations.accept', $token))->assertForbidden();
});

test('an invitation can be declined', function () {
    [$invitation, $token] = pendingInvitation();

    $this->post(route('invitations.decline', $token))->assertRedirect(route('login'));

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('an admin invitation grants the admin role', function () {
    [$invitation, $token, $workspace] = pendingInvitation('boss@playup.sk', WorkspaceRole::ADMIN);

    $this->post(route('invitations.accept', $token));

    $user = User::firstWhere('email', 'boss@playup.sk');

    expect($workspace->fresh()->getUserRole($user))->toBe(WorkspaceRole::ADMIN);
});

test('switching account logs out and returns to the invitation', function () {
    [, $token] = pendingInvitation();

    $someoneElse = User::factory()->active()->create(['email' => 'other@example.com']);

    $this->actingAs($someoneElse)
        ->post(route('invitations.switch-account', $token))
        ->assertRedirect(route('invitations.show', $token));

    $this->assertGuest();
});
