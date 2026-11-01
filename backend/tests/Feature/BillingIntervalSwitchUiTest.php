<?php

use App\Enums\WorkspaceRole;
use App\Events\WorkspaceUsageChanged;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * The switch between monthly and yearly lives on the workspace page, for owners and admins.
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
    Event::fake([WorkspaceUsageChanged::class]);
});

/**
 * @return array{0: Workspace, 1: User, 2: User}
 */
function intervalUiWorkspace(string $variant): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);

    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);

    Display::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_ui',
        'status' => 'active',
        'product_id' => '10',
        'variant_id' => $variant,
        'renews_at' => '2027-10-21 10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace, $owner, $member];
}

function viewWorkspacePage(User $user, Workspace $workspace)
{
    test()->actingAs($user);
    session()->put('selected_workspace_id', $workspace->id);

    return test()->get(route('workspaces.members'));
}

test('the owner of a monthly workspace is offered yearly', function () {
    [$workspace, $owner] = intervalUiWorkspace('11');

    viewWorkspacePage($owner, $workspace)
        ->assertOk()
        ->assertSee('Switch to yearly')
        ->assertSee('Pay a year in advance for your current 3 units. Your monthly subscription stops immediately and switches to a yearly subscription.')
        ->assertDontSee('Switch to monthly')
        ->assertDontSee('refund');
});

test('the owner of a yearly workspace is offered monthly', function () {
    [$workspace, $owner] = intervalUiWorkspace('12');

    viewWorkspacePage($owner, $workspace)
        ->assertOk()
        ->assertSee('Switch to monthly')
        ->assertDontSee('Switch to yearly');
});

test('a requested switch shows its date and can be taken back', function () {
    [$workspace, $owner] = intervalUiWorkspace('12');
    $workspace->forceFill(['switch_to_monthly_requested_at' => now()])->save();

    viewWorkspacePage($owner, $workspace)
        ->assertOk()
        ->assertSee('Your subscription switches to monthly on 21 October 2027.')
        ->assertSee('Keep yearly')
        ->assertDontSee('Switch to monthly');
});

test('a member sees no switch', function () {
    [$workspace, , $member] = intervalUiWorkspace('11');

    viewWorkspacePage($member, $workspace)
        ->assertOk()
        ->assertDontSee('Switch to yearly')
        ->assertDontSee('Switch to monthly');
});
