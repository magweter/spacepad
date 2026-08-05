<?php

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A usage change resizes the subscription by itself.
 *
 * The hourly app:update-lemonsqueezy-subscriptions run is the safety net for this, and is
 * covered separately in UpdateLemonSqueezyUsageTest. What matters here is that nobody has
 * to wait for it.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    cache()->flush();

    Http::fake([
        '*lemonsqueezy.com/v1/subscriptions/*' => Http::response([
            'data' => ['attributes' => ['subscription_items' => [['id' => 'item_1']]]],
        ]),
        '*lemonsqueezy.com/v1/subscription-items/*' => Http::response(['data' => []]),
        '*lemonsqueezy.com/v1/usage-records*' => Http::response(['data' => []]),
    ]);
});

function pushWorkspace(): Workspace
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

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

    return $workspace;
}

function lastPushedQuantity(): ?int
{
    $quantity = null;

    foreach (Http::recorded() as [$request]) {
        if (str_contains($request->url(), 'subscription-items/')) {
            $quantity = $request->data()['data']['attributes']['quantity'] ?? null;
        }
    }

    return $quantity;
}

test('creating a display resizes the subscription straight away', function () {
    $workspace = pushWorkspace();

    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    expect(lastPushedQuantity())->toBe(2);
});

test('a board is worth two units on the push as well', function () {
    $workspace = pushWorkspace();

    Display::factory()->create(['workspace_id' => $workspace->id]);
    Board::factory()->create(['workspace_id' => $workspace->id]);

    expect(lastPushedQuantity())->toBe(3);
});

test('deleting a display shrinks the subscription', function () {
    $workspace = pushWorkspace();

    $display = Display::factory()->create(['workspace_id' => $workspace->id]);
    $display->delete();

    expect(lastPushedQuantity())->toBe(0);
});

test('a manually billed workspace never reaches the API', function () {
    $workspace = pushWorkspace();
    $workspace->update(['is_manually_billed' => true]);

    Display::factory()->create(['workspace_id' => $workspace->id]);

    Http::assertNothingSent();
});

test('an unlimited workspace never reaches the API', function () {
    $workspace = pushWorkspace();
    $workspace->update(['is_unlimited' => true]);

    Display::factory()->create(['workspace_id' => $workspace->id]);

    Http::assertNothingSent();
});

test('a workspace with no subscription has nothing to resize', function () {
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    Display::factory()->create(['workspace_id' => $workspace->id]);

    Http::assertNothingSent();
});

test('a self-hosted instance pushes nothing', function () {
    $workspace = pushWorkspace();
    config(['settings.is_self_hosted' => true]);

    Display::factory()->create(['workspace_id' => $workspace->id]);

    Http::assertNothingSent();
});
