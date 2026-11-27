<?php

use App\Enums\DisplayStatus;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;
use LemonSqueezy\Laravel\Events\SubscriptionExpired;
use LemonSqueezy\Laravel\Subscription;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * When the free plan's one active display takes effect: on a plan change from now on, never
 * for a workspace that was already free.
 */

/**
 * @return array{0: Workspace, 1: User, 2: Display, 3: Display}
 */
function workspaceWithTwoLiveDisplays(): array
{
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    $first = Display::factory()->active()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);
    $first->forceFill(['created_at' => '2026-01-01'])->saveQuietly();
    $second = Display::factory()->active()->create(['user_id' => $owner->id, 'workspace_id' => $workspace->id]);
    $second->forceFill(['created_at' => '2026-02-01'])->saveQuietly();

    return [$workspace, $owner, $first, $second];
}

function subscriptionFor(Workspace $workspace, array $attributes): Subscription
{
    DB::table('lemon_squeezy_subscriptions')->insert(array_merge([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_'.uniqid(),
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));

    return Subscription::query()->latest('id')->first();
}

test('a subscription that expires leaves one active display', function () {
    [$workspace, , $first, $second] = workspaceWithTwoLiveDisplays();
    $subscription = subscriptionFor($workspace, ['status' => 'expired', 'ends_at' => now()->subHour()]);

    event(new SubscriptionExpired($workspace, $subscription, []));

    expect($first->fresh()->status)->toBe(DisplayStatus::ACTIVE)
        ->and($second->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('a new subscription brings waiting displays back', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();
    $second->update(['status' => DisplayStatus::REQUIRES_PRO]);
    $subscription = subscriptionFor($workspace, ['status' => 'active']);

    event(new SubscriptionCreated($workspace, $subscription, []));

    expect($second->fresh()->status)->toBe(DisplayStatus::READY);
});

test('a failed payment does not touch any display', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();
    subscriptionFor($workspace, ['status' => 'unpaid']);

    $this->artisan('app:free-plan-displays', ['--sync' => true])->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('the scheduled catch-up handles a subscription that ran out without a webhook', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();
    subscriptionFor($workspace, ['status' => 'cancelled', 'ends_at' => now()->subHours(3)]);

    $this->artisan('app:free-plan-displays', ['--sync' => true])->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});

test('the scheduled catch-up leaves workspaces that were already free alone', function () {
    [, , , $second] = workspaceWithTwoLiveDisplays();

    $this->artisan('app:free-plan-displays', ['--sync' => true])->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('the scheduled catch-up brings displays back for a workspace that has Pro', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();
    $second->update(['status' => DisplayStatus::REQUIRES_PRO]);
    subscriptionFor($workspace, ['status' => 'active']);

    $this->artisan('app:free-plan-displays', ['--sync' => true])->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::READY);
});

test('listing changes nothing', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();

    $this->artisan('app:free-plan-displays', ['--list' => true])
        ->expectsOutputToContain($workspace->id)
        ->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('applying to one workspace only changes that workspace', function () {
    [$workspace, , , $second] = workspaceWithTwoLiveDisplays();
    [, , , $otherSecond] = workspaceWithTwoLiveDisplays();

    $this->artisan('app:free-plan-displays', ['--workspace' => $workspace->id])->assertSuccessful();

    expect($second->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO)
        ->and($otherSecond->fresh()->status)->toBe(DisplayStatus::ACTIVE);
});

test('combining two free workspaces leaves one active display', function () {
    [$target, , $targetDisplay] = workspaceWithTwoLiveDisplays();
    $targetDisplay->fresh()->workspace->displays()->whereKeyNot($targetDisplay->id)->delete();

    $sourceOwner = User::factory()->active()->create();
    $source = $sourceOwner->primaryWorkspace();
    $moved = Display::factory()->active()->create(['user_id' => $sourceOwner->id, 'workspace_id' => $source->id]);

    $admin = User::factory()->active()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.merge.store'), [
        'source_workspace_id' => $source->id,
        'target_workspace_id' => $target->id,
        'confirm_name' => $source->name,
    ]);

    expect($targetDisplay->fresh()->status)->toBe(DisplayStatus::ACTIVE)
        ->and($moved->fresh()->workspace_id)->toBe($target->id)
        ->and($moved->fresh()->status)->toBe(DisplayStatus::REQUIRES_PRO);
});
