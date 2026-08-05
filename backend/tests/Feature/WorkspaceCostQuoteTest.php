<?php

use App\Enums\DisplayStatus;
use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * What the subscription card on Manage workspace is allowed to quote. Two billing routes with
 * two prices in two currencies: Lemon Squeezy charges the list price in dollars, we invoice
 * manually billed workspaces in euro at whatever price was agreed with them. Quoting the
 * wrong one contradicts the invoice the customer receives.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.unit_price' => 6,
    ]);
});

function ownedWorkspace(array $attributes = [], int $displays = 1): array
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create($attributes + ['billing_owner_user_id' => $owner->id]);
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

    return [$workspace->fresh(), $owner];
}

function visitWorkspacePage(Workspace $workspace, User $owner)
{
    test()->actingAs($owner);
    session()->put('selected_workspace_id', $workspace->id);

    return test()->get(route('workspaces.members'));
}

test('a Lemon Squeezy workspace is quoted the list price in dollars', function () {
    [$workspace, $owner] = ownedWorkspace(displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertSee('$6.00')
        ->assertDontSee('€');
});

test('a subscribed workspace is quoted what Lemon Squeezy actually charges', function () {
    // The analytics table is cloud-only, so its migration skips creation in the test env.
    $migration = require database_path('migrations/2026_08_05_000003_create_analytics_workspaces_table.php');
    $migration->up();

    [$workspace, $owner] = ownedWorkspace(displays: 1);

    // 4 units for $10 a month at the last refresh, so $2.50 per unit and not the $6 list price.
    DB::table('analytics_workspaces')->insert([
        'workspace_id' => $workspace->id,
        'units' => 4,
        'mrr_current' => 10,
    ]);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertSee('$2.50')
        ->assertDontSee('$6.00');
});

test('the list price stands in when the actual price is not known yet', function () {
    $migration = require database_path('migrations/2026_08_05_000003_create_analytics_workspaces_table.php');
    $migration->up();

    [$workspace, $owner] = ownedWorkspace(displays: 1);

    // A workspace the refresh has seen but that pays nothing yet, a trial without a resolved
    // price among them. Zero is not a price to quote.
    DB::table('analytics_workspaces')->insert([
        'workspace_id' => $workspace->id,
        'units' => 1,
        'mrr_current' => 0,
    ]);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertSee('$6.00');
});

test('a manually billed workspace is quoted its negotiated price in euro', function () {
    // The non-profit case: a discounted price we invoice ourselves.
    [$workspace, $owner] = ownedWorkspace([
        'is_manually_billed' => true,
        'manual_billing_unit_price' => 3,
    ], displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertSee('€3.00')
        ->assertDontSee('$3.00')
        ->assertDontSee('$6.00')
        // No Lemon Squeezy subscription exists, so it must not send them to their order email.
        ->assertDontSee('Manage subscription')
        ->assertSee('We invoice this workspace directly');
});

test('a manually billed workspace without a negotiated price falls back to the list price', function () {
    [$workspace, $owner] = ownedWorkspace([
        'is_manually_billed' => true,
        'manual_billing_unit_price' => null,
    ], displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertSee('€6.00');
});

test('an unlimited workspace is quoted nothing', function () {
    [$workspace, $owner] = ownedWorkspace(['is_unlimited' => true], displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertDontSee('per month')
        ->assertSee('This workspace has Pro at no charge.');
});

test('a self-hosted instance is quoted nothing', function () {
    config(['settings.is_self_hosted' => true]);

    [$workspace, $owner] = ownedWorkspace(displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertDontSee('per month');
});

test('no unit price configured means no quote at all', function () {
    config(['settings.unit_price' => null]);

    [$workspace, $owner] = ownedWorkspace(displays: 1);

    visitWorkspacePage($workspace, $owner)
        ->assertOk()
        ->assertDontSee('per month');
});

test('the usage total still drives the amount', function () {
    // 2 displays (1x) + 1 board (2x) = 4 units at 6.
    [$workspace, $owner] = ownedWorkspace(displays: 2);
    Board::factory()->create([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
    ]);

    visitWorkspacePage($workspace->fresh(), $owner)
        ->assertOk()
        ->assertSee('$24.00');
});
