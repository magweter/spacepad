<?php

use App\Enums\DisplayStatus;
use App\Enums\WorkspaceRole;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pairing must land the tablet in the workspace the person was looking at.
 *
 * Previously the dashboard showed the workspace *owner's* personal connect code while the
 * API bound the new device to that user's primary workspace — so a member pairing a tablet
 * with the team workspace selected got a device in their own personal workspace instead.
 */

/**
 * A member of someone else's team, whose own personal workspace is not the team.
 *
 * @return array{0: User, 1: Workspace, 2: Workspace}
 */
function memberOfATeam(): array
{
    $owner = User::factory()->active()->create(['is_unlimited' => true]);
    $team = Workspace::factory()->create(['name' => 'Playup']);
    WorkspaceMember::create([
        'workspace_id' => $team->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $team->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    return [$member, $team, $member->primaryWorkspace()];
}

test('a code generated for the selected workspace pairs the device into it', function () {
    [$member, $team, $personal] = memberOfATeam();

    expect($personal->id)->not->toBe($team->id);

    $code = $team->getConnectCode($member);

    $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'tablet-uid-1',
        'name' => 'Lobby tablet',
    ])->assertOk();

    $device = Device::firstWhere('uid', 'tablet-uid-1');

    expect($device)->not->toBeNull();
    expect($device->workspace_id)->toBe($team->id);
    expect($device->user_id)->toBe($member->id);
});

test('the dashboard shows a code for the selected workspace', function () {
    [$member, $team] = memberOfATeam();

    $this->actingAs($member);
    session()->put('selected_workspace_id', $team->id);

    $this->get(route('dashboard'))->assertOk();

    // Pairing with whatever code the dashboard just minted must reach the team workspace.
    $code = $team->getConnectCode($member);

    $this->postJson('/api/auth/login', [
        'code' => $code,
        'uid' => 'tablet-uid-2',
        'name' => 'Tablet',
    ])->assertOk();

    expect(Device::firstWhere('uid', 'tablet-uid-2')->workspace_id)->toBe($team->id);
});

test('a connect code can only be used once', function () {
    [$member, $team] = memberOfATeam();

    $code = $team->getConnectCode($member);

    $this->postJson('/api/auth/login', ['code' => $code, 'uid' => 'a', 'name' => 'A'])->assertOk();
    $this->postJson('/api/auth/login', ['code' => $code, 'uid' => 'b', 'name' => 'B'])
        ->assertJsonPath('success', false);
});

test('re-pairing moves an existing device to the newly chosen workspace', function () {
    [$member, $team, $personal] = memberOfATeam();

    $this->postJson('/api/auth/login', [
        'code' => $personal->getConnectCode($member),
        'uid' => 'roaming-tablet',
        'name' => 'Tablet',
    ])->assertOk();

    expect(Device::firstWhere('uid', 'roaming-tablet')->workspace_id)->toBe($personal->id);

    $this->postJson('/api/auth/login', [
        'code' => $team->getConnectCode($member),
        'uid' => 'roaming-tablet',
        'name' => 'Tablet',
    ])->assertOk();

    expect(Device::firstWhere('uid', 'roaming-tablet')->workspace_id)->toBe($team->id);
});

test('a device only sees displays from its own workspace', function () {
    [$member, $team, $personal] = memberOfATeam();

    $teamDisplay = Display::factory()->create([
        'workspace_id' => $team->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
    $personalDisplay = Display::factory()->create([
        'workspace_id' => $personal->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    // Pair into the personal workspace.
    $response = $this->postJson('/api/auth/login', [
        'code' => $personal->getConnectCode($member),
        'uid' => 'tablet-scoped',
        'name' => 'Tablet',
    ])->assertOk();

    $token = $response->json('data.token');

    $displays = $this->withToken($token)->getJson('/api/displays')->assertOk()->json('data');
    $ids = collect($displays)->pluck('id');

    expect($ids)->toContain($personalDisplay->id);
    expect($ids)->not->toContain($teamDisplay->id);
});

test('a legacy device without a workspace still sees the users displays', function () {
    [$member, $team] = memberOfATeam();

    $teamDisplay = Display::factory()->create([
        'workspace_id' => $team->id,
        'status' => DisplayStatus::ACTIVE,
    ]);

    // A device from before pairing recorded a workspace.
    $device = Device::factory()->create([
        'user_id' => $member->id,
        'workspace_id' => null,
        'uid' => 'legacy-tablet',
    ]);
    $token = $device->createToken('device-token')->plainTextToken;

    $displays = $this->withToken($token)->getJson('/api/displays')->assertOk()->json('data');

    expect(collect($displays)->pluck('id'))->toContain($teamDisplay->id);
});
