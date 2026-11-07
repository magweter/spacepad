<?php

use App\Events\UserRegistered;
use App\Models\Display;
use App\Models\FunnelEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FunnelTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LemonSqueezy\Laravel\Events\SubscriptionCreated;
use LemonSqueezy\Laravel\Subscription;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

/**
 * The funnel from signing up to paying, recorded in our own database per workspace.
 */
function funnelSteps(Workspace $workspace): array
{
    return FunnelEvent::where('workspace_id', $workspace->id)->orderBy('id')->pluck('event')->all();
}

test('signing up is recorded once, dated when the account was created', function () {
    $user = User::factory()->active()->create(['created_at' => now()->subDays(3)]);
    $workspace = $user->primaryWorkspace();

    event(new UserRegistered($user));
    event(new UserRegistered($user));

    $events = FunnelEvent::where('workspace_id', $workspace->id)->where('event', 'signed_up')->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->user_id)->toBe($user->id)
        ->and($events->first()->occurred_at->isSameDay(now()->subDays(3)))->toBeTrue();
});

test('the first calendar and the first display are recorded once', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $this->actingAs($user);

    FunnelTracking::calendarConnected($workspace, true);
    Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);
    FunnelTracking::displayCreated($workspace);
    FunnelTracking::displayCreated($workspace);

    expect(funnelSteps($workspace))->toBe(['calendar_connected', 'display_created']);
});

test('pairing a tablet is recorded the moment it happens', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    $this->postJson('/api/auth/login', [
        'code' => $workspace->getConnectCode($user),
        'uid' => 'tablet-1',
        'name' => 'Lobby tablet',
    ])->assertOk();

    expect(funnelSteps($workspace))->toBe(['device_paired']);
});

test('a subscription is recorded with its status', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_funnel',
        'status' => 'on_trial',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    event(new SubscriptionCreated($workspace, Subscription::sole(), []));

    $event = FunnelEvent::where('workspace_id', $workspace->id)->where('event', 'subscribed')->sole();
    expect($event->properties)->toBe(['status' => 'on_trial']);
});
