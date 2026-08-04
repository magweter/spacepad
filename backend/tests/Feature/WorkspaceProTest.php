<?php

use App\Enums\UsageType;
use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * Pro is a property of a workspace, not of a person.
 */

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function proWorkspaceWithMember(): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::MEMBER,
    ]);

    return [$workspace, $owner, $member];
}

test('is_unlimited on the workspace grants Pro', function () {
    [$workspace] = proWorkspaceWithMember();

    expect($workspace->hasPro())->toBeFalse();

    $workspace->update(['is_unlimited' => true]);

    expect($workspace->fresh()->hasPro())->toBeTrue();
});

test('is_unlimited on the owner alone does not grant Pro', function () {
    [$workspace, $owner] = proWorkspaceWithMember();

    // The flag now has to be on the workspace: the money follows the workspace.
    $owner->update(['is_unlimited' => true]);

    expect($workspace->fresh()->hasPro())->toBeFalse();
});

test('a manually billed workspace has Pro', function () {
    [$workspace] = proWorkspaceWithMember();

    $workspace->update(['is_manually_billed' => true]);

    expect($workspace->fresh()->hasPro())->toBeTrue();
});

test('an active subscription attached to the workspace grants Pro', function () {
    [$workspace] = proWorkspaceWithMember();

    DB::table('lemon_squeezy_subscriptions')->insert([
        'id' => 1,
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_1',
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($workspace->fresh()->hasPro())->toBeTrue();
});

test('an expired subscription does not grant Pro', function () {
    [$workspace] = proWorkspaceWithMember();

    DB::table('lemon_squeezy_subscriptions')->insert([
        'id' => 1,
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_1',
        'status' => 'expired',
        'product_id' => '1',
        'variant_id' => '1',
        'ends_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($workspace->fresh()->hasPro())->toBeFalse();
});

test('a member of a Pro workspace has Pro there but not in their own', function () {
    [$workspace, , $member] = proWorkspaceWithMember();
    $workspace->update(['is_unlimited' => true]);

    expect($member->hasProForWorkspace($workspace->fresh()))->toBeTrue();
    expect($member->hasProForWorkspace($member->primaryWorkspace()))->toBeFalse();
});

test('hasProForCurrentWorkspace follows the selected workspace', function () {
    [$workspace, , $member] = proWorkspaceWithMember();
    $workspace->update(['is_unlimited' => true]);

    $this->actingAs($member);

    session()->put('selected_workspace_id', $workspace->id);
    expect($member->hasProForCurrentWorkspace())->toBeTrue();

    session()->put('selected_workspace_id', $member->primaryWorkspace()->id);
    expect($member->hasProForCurrentWorkspace())->toBeFalse();
});

test('self-hosted Pro comes from the licence or a personal owner', function () {
    config(['settings.is_self_hosted' => true]);

    [$workspace, $owner] = proWorkspaceWithMember();

    $owner->update(['usage_type' => UsageType::BUSINESS]);
    expect($workspace->fresh()->hasPro())->toBeFalse();

    $owner->update(['usage_type' => UsageType::PERSONAL]);
    expect($workspace->fresh()->hasPro())->toBeTrue();
});

test('the upgrade prompt considers every display in the workspace', function () {
    [$workspace, , $member] = proWorkspaceWithMember();

    // A display created by the owner, not by this member. The old creator-scoped check meant
    // an invited member never saw the prompt.
    Display::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $workspace->owners()->first()->id,
    ]);

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    expect($member->shouldUpgradeForCurrentWorkspace())->toBeTrue();

    $workspace->update(['is_unlimited' => true]);
    expect($member->fresh()->shouldUpgradeForCurrentWorkspace())->toBeFalse();
});

test('the usage formula lives in one place', function () {
    expect(Workspace::calculateUsage(3, 0))->toBe(3);
    expect(Workspace::calculateUsage(0, 2))->toBe(4);
    expect(Workspace::calculateUsage(2, 1))->toBe(4);
});

test('lemon squeezy is given the billing owner identity, not the workspace name', function () {
    [$workspace, $owner] = proWorkspaceWithMember();

    expect($workspace->lemonSqueezyEmail())->toBe($owner->email);
    expect($workspace->lemonSqueezyName())->toBe($owner->name);
    expect($workspace->lemonSqueezyName())->not->toBe($workspace->name);
});

test('an explicit billing owner wins over the earliest owner', function () {
    [$workspace, $owner, $member] = proWorkspaceWithMember();

    // Promote the member and record them as the payer.
    WorkspaceMember::where('workspace_id', $workspace->id)
        ->where('user_id', $member->id)
        ->update(['role' => WorkspaceRole::OWNER]);
    $workspace->update(['billing_owner_user_id' => $member->id]);

    expect($workspace->fresh()->billingOwner()->id)->toBe($member->id);
});
