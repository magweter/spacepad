<?php

use App\Enums\DisplayStatus;
use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Fill a workspace with one of everything and return the models.
 */
function populateWorkspace(Workspace $workspace, User $creator): array
{
    $account = OutlookAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
    ]);
    $caldav = CalDAVAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
    ]);
    $profile = DisplayProfile::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
    ]);
    $display = Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
        'status' => DisplayStatus::ACTIVE,
        'display_profile_id' => $profile->id,
    ]);
    $device = Device::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
    ]);
    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $creator->id,
    ]);
    $board->displays()->attach($display->id, ['id' => (string) Str::ulid()]);

    return compact('account', 'caldav', 'profile', 'display', 'device', 'board');
}

test('moving a workspace carries every workspace-scoped row across', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create(['name' => 'Playup']);

    $models = populateWorkspace($source, $user);

    app(WorkspaceTransferService::class)->move($source, $target, $user);

    foreach ($models as $name => $model) {
        expect($model->fresh()->workspace_id)->toBe($target->id, "{$name} should have moved");
    }

    expect($source->fresh()->isEmpty())->toBeTrue();
});

test('the move leaves user_id untouched', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create();

    $models = populateWorkspace($source, $user);

    app(WorkspaceTransferService::class)->move($source, $target, $user);

    foreach ($models as $name => $model) {
        expect($model->fresh()->user_id)->toBe($user->id, "{$name} should keep its creator");
    }
});

test('board links and profile links survive a whole-workspace move', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create();

    ['board' => $board, 'display' => $display, 'profile' => $profile] = populateWorkspace($source, $user);

    app(WorkspaceTransferService::class)->move($source, $target, $user);

    // Both sides moved together, so the pivot and the profile link stay valid.
    expect(DB::table('board_displays')->where('board_id', $board->id)->where('display_id', $display->id)->exists())->toBeTrue();
    expect($display->fresh()->display_profile_id)->toBe($profile->id);
    expect($board->fresh()->getDisplaysToShow()->pluck('id')->all())->toContain($display->id);
});

test('a display is unlinked from a profile left behind in another workspace', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create();

    // A profile that belongs to a third workspace: the display must not keep inheriting it.
    $foreignProfile = DisplayProfile::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
    ]);
    $display = Display::factory()->create([
        'workspace_id' => $source->id,
        'user_id' => $user->id,
        'display_profile_id' => $foreignProfile->id,
    ]);

    app(WorkspaceTransferService::class)->move($source, $target, $user);

    expect($display->fresh()->display_profile_id)->toBeNull();
});

test('an unrelated workspace is untouched', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create();

    $bystander = Workspace::factory()->create();
    $bystanderDisplay = Display::factory()->create(['workspace_id' => $bystander->id]);

    populateWorkspace($source, $user);
    app(WorkspaceTransferService::class)->move($source, $target, $user);

    expect($bystanderDisplay->fresh()->workspace_id)->toBe($bystander->id);
});

test('orphaned rows are only adopted when asked for', function () {
    $user = User::factory()->active()->create();
    $source = $user->primaryWorkspace();
    $target = Workspace::factory()->create();

    $orphan = Display::factory()->create([
        'workspace_id' => null,
        'user_id' => $user->id,
    ]);

    app(WorkspaceTransferService::class)->move($source, $target, $user, adoptOrphans: false);
    expect($orphan->fresh()->workspace_id)->toBeNull();

    app(WorkspaceTransferService::class)->move($source, $target, $user, adoptOrphans: true);
    expect($orphan->fresh()->workspace_id)->toBe($target->id);
});

test('accepting an invitation can bring the invitees data along', function () {
    $inviteeEmail = 'trnka@playup.sk';

    $owner = User::factory()->active()->create(['is_unlimited' => true]);
    $team = Workspace::factory()->create(['name' => 'Playup']);
    WorkspaceMember::create([
        'workspace_id' => $team->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $invitee = User::factory()->active()->create(['email' => $inviteeEmail]);
    $ownWorkspace = $invitee->primaryWorkspace();
    ['display' => $display, 'board' => $board] = populateWorkspace($ownWorkspace, $invitee);

    $plainToken = Str::random(64);
    WorkspaceInvitation::create([
        'workspace_id' => $team->id,
        'invited_by_user_id' => $owner->id,
        'email' => $inviteeEmail,
        'role' => WorkspaceRole::MEMBER,
        'token' => WorkspaceInvitation::hashToken($plainToken),
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $plainToken), [
            'move_data' => '1',
            'source_workspace_id' => $ownWorkspace->id,
        ])
        ->assertRedirect(route('dashboard'));

    expect($display->fresh()->workspace_id)->toBe($team->id);
    expect($board->fresh()->workspace_id)->toBe($team->id);
    expect($ownWorkspace->fresh()->isEmpty())->toBeTrue();
});

test('a hostile source workspace id is ignored', function () {
    $victim = User::factory()->active()->create();
    $victimWorkspace = $victim->primaryWorkspace();
    $victimDisplay = Display::factory()->create([
        'workspace_id' => $victimWorkspace->id,
        'user_id' => $victim->id,
    ]);

    $owner = User::factory()->active()->create(['is_unlimited' => true]);
    $team = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $team->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $attacker = User::factory()->active()->create(['email' => 'attacker@playup.sk']);

    $plainToken = Str::random(64);
    WorkspaceInvitation::create([
        'workspace_id' => $team->id,
        'invited_by_user_id' => $owner->id,
        'email' => 'attacker@playup.sk',
        'role' => WorkspaceRole::MEMBER,
        'token' => WorkspaceInvitation::hashToken($plainToken),
        'expires_at' => now()->addDays(7),
    ]);

    // Points the move at a workspace they do not own.
    $this->actingAs($attacker)
        ->post(route('invitations.accept', $plainToken), [
            'move_data' => '1',
            'source_workspace_id' => $victimWorkspace->id,
        ])
        ->assertRedirect(route('dashboard'));

    expect($victimDisplay->fresh()->workspace_id)->toBe($victimWorkspace->id);
});
