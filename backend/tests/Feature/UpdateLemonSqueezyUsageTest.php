<?php

use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    cache()->flush();
});

/**
 * Fake the Lemon Squeezy endpoints this command touches.
 *
 * Declared per test rather than in beforeEach: Http::fake() merges stubs and the first
 * match wins, so a later fake cannot override an earlier one.
 */
function fakeLemonSqueezy(int $pushStatus = 200): void
{
    Http::fake([
        '*lemonsqueezy.com/v1/subscriptions/*' => Http::response([
            'data' => ['attributes' => ['subscription_items' => [['id' => 'item_1']]]],
        ]),
        '*lemonsqueezy.com/v1/subscription-items/*' => Http::response(['data' => []], $pushStatus),
        '*lemonsqueezy.com/v1/usage-records*' => Http::response(['data' => []], $pushStatus),
    ]);
}

/**
 * A workspace with a live subscription attached.
 */
function subscribedWorkspace(User $owner, int $id = 1): Workspace
{
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);

    DB::table('lemon_squeezy_subscriptions')->insert([
        'id' => $id,
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => "sub_{$id}",
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $workspace;
}

/**
 * The quantity that was actually pushed.
 */
function pushedQuantity(): ?int
{
    $quantity = null;

    foreach (Http::recorded() as [$request]) {
        if (str_contains($request->url(), 'subscription-items/')) {
            $quantity = $request->data()['data']['attributes']['quantity'] ?? null;
        }
    }

    return $quantity;
}

test('usage is pushed for the workspace only, not summed across memberships', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();

    // Owns A with 2 displays.
    $ownWorkspace = subscribedWorkspace($owner);
    Display::factory()->count(2)->create(['workspace_id' => $ownWorkspace->id]);

    // And is a member of B with 5 displays, which is not theirs to pay for.
    $colleagueWorkspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $colleagueWorkspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::MEMBER,
    ]);
    Display::factory()->count(5)->create(['workspace_id' => $colleagueWorkspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    // The old rule summed every membership and pushed 7.
    expect(pushedQuantity())->toBe(2);
});

test('boards are weighted double', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);

    Display::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    Board::factory()->create(['workspace_id' => $workspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    expect(pushedQuantity())->toBe(4);
});

test('a manually billed workspace is never pushed', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);
    $workspace->update(['is_manually_billed' => true]);

    Display::factory()->create(['workspace_id' => $workspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    Http::assertNothingSent();
});

test('an unlimited workspace is never pushed', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);
    $workspace->update(['is_unlimited' => true]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    Http::assertNothingSent();
});

test('a dry run reports without calling the API', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);
    Display::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions', ['--dry-run' => true]);

    Http::assertNothingSent();
    expect(Artisan::output())->toContain('units=3');
});

test('a failing API push is reported as an error rather than a success', function () {
    fakeLemonSqueezy(pushStatus: 500);

    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);
    Display::factory()->create(['workspace_id' => $workspace->id]);

    // Previously both failures went to Log::debug and the run still exited zero.
    $exitCode = Artisan::call('app:update-lemonsqueezy-subscriptions');

    expect($exitCode)->toBe(1);
});

test('nothing is pushed on a self-hosted instance', function () {
    fakeLemonSqueezy();
    config(['settings.is_self_hosted' => true]);

    $owner = User::factory()->active()->create();
    $workspace = subscribedWorkspace($owner);
    Display::factory()->create(['workspace_id' => $workspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    Http::assertNothingSent();
});

test('a workspace without a subscription is skipped', function () {
    fakeLemonSqueezy();
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::OWNER,
    ]);
    Display::factory()->create(['workspace_id' => $workspace->id]);

    Artisan::call('app:update-lemonsqueezy-subscriptions');

    Http::assertNothingSent();
});
