<?php

use App\Enums\PreviousSolution;
use App\Enums\RoomsEstimate;
use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * Two optional questions on the dashboard, after the first display: how many rooms, and
 * what the workspace used before.
 */
const PROFILE_CARD = 'Two quick questions?';

/**
 * @return array{0: Workspace, 1: User}
 */
function businessWorkspaceWithDisplay(): array
{
    $owner = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    $workspace = $owner->primaryWorkspace();
    Display::factory()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);

    return [$workspace, $owner];
}

test('the owner sees the card after the first display', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your first display is live. Two quick questions?')
        ->assertSee('How many rooms do you want to show?')
        ->assertSee('Teams Rooms panel');

    expect($workspace->fresh()->profile_prompted_at)->not->toBeNull();
});

test('nobody is asked before the first display', function () {
    $owner = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);

    $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertDontSee(PROFILE_CARD);
});

test('a member, a personal user and a self-hosted instance are not asked', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $member = User::factory()->active()->create(['usage_type' => UsageType::BUSINESS]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
    expect($workspace->shouldAskProfile($member))->toBeFalse();

    $owner->update(['usage_type' => UsageType::PERSONAL]);
    expect($workspace->fresh()->shouldAskProfile($owner->fresh()))->toBeFalse();

    $owner->update(['usage_type' => UsageType::BUSINESS]);
    config(['settings.is_self_hosted' => true]);
    expect($workspace->fresh()->shouldAskProfile($owner->fresh()))->toBeFalse();
});

test('the first answer is saved on its own and the card stays', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)
        ->postJson(route('workspace.profile.update'), ['rooms_estimate' => '20+'])
        ->assertNoContent();

    $workspace->refresh();
    expect($workspace->rooms_estimate)->toBe(RoomsEstimate::TWENTY_PLUS)
        ->and($workspace->profile_answered_at)->toBeNull()
        ->and($workspace->shouldAskProfile($owner))->toBeTrue();
});

test('answering the second question ends the card', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)
        ->postJson(route('workspace.profile.update'), ['previous_solution' => 'joan'])
        ->assertNoContent();

    $workspace->refresh();
    expect($workspace->previous_solution)->toBe(PreviousSolution::JOAN)
        ->and($workspace->profile_answered_at)->not->toBeNull()
        ->and($workspace->shouldAskProfile($owner))->toBeFalse();
});

test('something else needs a name', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)
        ->postJson(route('workspace.profile.update'), ['previous_solution' => 'other'])
        ->assertUnprocessable();

    $this->actingAs($owner)
        ->postJson(route('workspace.profile.update'), ['previous_solution' => 'other', 'previous_solution_other' => 'Meetio'])
        ->assertNoContent();

    expect($workspace->fresh()->previous_solution_other)->toBe('Meetio');
});

test('an unknown answer is refused', function () {
    [, $owner] = businessWorkspaceWithDisplay();

    $this->actingAs($owner)
        ->postJson(route('workspace.profile.update'), ['rooms_estimate' => '500'])
        ->assertUnprocessable();
});

test('closing the card keeps earlier answers and hides it for good', function () {
    [$workspace, $owner] = businessWorkspaceWithDisplay();
    $workspace->update(['rooms_estimate' => '6-20']);

    $this->actingAs($owner)->postJson(route('workspace.profile.dismiss'))->assertNoContent();

    $workspace->refresh();
    expect($workspace->rooms_estimate)->toBe(RoomsEstimate::SIX_TO_TWENTY)
        ->and($workspace->shouldAskProfile($owner))->toBeFalse();

    $this->actingAs($owner)->get(route('dashboard'))->assertDontSee(PROFILE_CARD);
});

test('a member cannot answer for the workspace', function () {
    [$workspace] = businessWorkspaceWithDisplay();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    $this->actingAs($member)
        ->withSession(['selected_workspace_id' => $workspace->id])
        ->postJson(route('workspace.profile.update'), ['rooms_estimate' => '1'])
        ->assertForbidden();

    expect($workspace->fresh()->rooms_estimate)->toBeNull();
});
