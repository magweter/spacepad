<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A yearly customer asks to go back to monthly. Nothing changes at Lemon Squeezy until the
 * day before the paid year ends (app:process-yearly-subscriptions), so asking and changing
 * your mind are both just a flag on the workspace.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.pro_switch_product_id' => '10',
        'settings.pro_switch_monthly_variant_id' => '11',
        'settings.pro_switch_yearly_variant_id' => '12',
        'settings.pro_yearly_variant_ids' => [],
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    Http::fake();
});

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function yearlyWorkspace(string $variant = '12'): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_yearly',
        'status' => 'active',
        'product_id' => '10',
        'variant_id' => $variant,
        'renews_at' => now()->addMonths(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace, $owner, $member];
}

test('the owner can ask to go back to monthly', function () {
    [$workspace, $owner] = yearlyWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-monthly'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('success');

    expect($workspace->fresh()->switch_to_monthly_requested_at)->not->toBeNull();
    Http::assertNothingSent();
});

test('the owner can change their mind before the year ends', function () {
    [$workspace, $owner] = yearlyWorkspace();
    $workspace->forceFill(['switch_to_monthly_requested_at' => now()])->save();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->delete(route('billing.switch-monthly.cancel'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('success');

    expect($workspace->fresh()->switch_to_monthly_requested_at)->toBeNull();
});

test('a member can do neither', function () {
    [$workspace, , $member] = yearlyWorkspace();

    $this->actingAs($member);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-monthly'))->assertForbidden();
    $this->delete(route('billing.switch-monthly.cancel'))->assertForbidden();
});

test('a monthly workspace cannot ask to go monthly', function () {
    [$workspace, $owner] = yearlyWorkspace(variant: '11');

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.switch-monthly'))
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('error');

    expect($workspace->fresh()->switch_to_monthly_requested_at)->toBeNull();
});

test('a cancelled yearly subscription cannot be switched', function () {
    [$workspace, $owner] = yearlyWorkspace();
    DB::table('lemon_squeezy_subscriptions')->update(['status' => 'cancelled', 'ends_at' => now()->addMonth()]);

    expect($workspace->fresh()->canSwitchToMonthly())->toBeFalse();
});
