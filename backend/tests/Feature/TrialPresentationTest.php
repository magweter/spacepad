<?php

use App\Enums\DisplayStatus;
use App\Enums\WorkspaceRole;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A trial is an ordinary Lemon Squeezy subscription: the payment method is already on file
 * and it converts on its own at trial_ends_at. So a trialling workspace is an activated
 * workspace, and telling its owner to "subscribe now" is both untrue and dangerous — acting
 * on it buys a second subscription for the same workspace.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.cloud_hosted_pro_plan_id' => '12345',
        'settings.unit_price' => 6,
        'lemon-squeezy.api_key' => 'test-key',
        'lemon-squeezy.store' => 'teststore',
    ]);
});

function trialWorkspace(int $displays = 2, int $daysLeft = 9): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create(['billing_owner_user_id' => $owner->id]);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    for ($i = 0; $i < $displays; $i++) {
        Display::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'status' => DisplayStatus::ACTIVE,
        ]);
    }

    DB::table('lemon_squeezy_subscriptions')->insert([
        'id' => 1,
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_trial',
        'status' => 'on_trial',
        'product_id' => '1',
        'variant_id' => '12345',
        'trial_ends_at' => now()->addDays($daysLeft),
        'renews_at' => now()->addDays($daysLeft),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace->fresh(), $owner];
}

test('a trialling workspace counts as activated', function () {
    [$workspace] = trialWorkspace();

    expect($workspace->hasPro())->toBeTrue()
        ->and($workspace->subscribed())->toBeTrue();
});

test('the dashboard does not push a trialling owner to subscribe', function () {
    [$workspace, $owner] = trialWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee('Subscribe now')
        ->assertDontSee('Your trial expires')
        ->assertDontSee('Upgrade to Pro');
});

test('the workspace page shows the trial countdown and what it will cost', function () {
    [$workspace, $owner] = trialWorkspace(displays: 2, daysLeft: 9);

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $response = $this->get(route('workspaces.members'));

    // 2 displays = 2 units, at 6 per unit.
    $response->assertOk()
        ->assertSee('9 days left of your trial')
        ->assertSee('Your subscription starts automatically on')
        ->assertSee('12.00');
});

test('the cost estimate is left out when no unit price is configured', function () {
    config(['settings.unit_price' => null]);

    [$workspace, $owner] = trialWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->get(route('workspaces.members'))
        ->assertOk()
        ->assertSee('days left of your trial')
        ->assertDontSee('per month');
});

test('a workspace that already subscribes cannot start a second checkout', function () {
    Http::fake();

    [$workspace, $owner] = trialWorkspace();

    $this->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    $this->post(route('billing.checkout'))
        ->assertRedirect(route('workspaces.members'));

    // The guard has to bite before the vendor asks Lemon Squeezy to mint a checkout.
    Http::assertNothingSent();
});
