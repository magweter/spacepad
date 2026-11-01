<?php

use App\Enums\CancellationReason;
use App\Enums\WorkspaceRole;
use App\Models\SubscriptionCancellation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LemonSqueezy\Laravel\Events\SubscriptionCancelled;
use LemonSqueezy\Laravel\Events\SubscriptionResumed;
use LemonSqueezy\Laravel\Subscription;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'lemon-squeezy.api_key' => 'test-key',
        'lemon-squeezy.store' => 'teststore',
    ]);
});

/**
 * A subscription is cancelled from the workspace page, and the app asks why first.
 */

/**
 * @return array{0: Workspace, 1: User, 2: Subscription}
 */
function workspaceWithSubscriptionToCancel(string $status = 'active'): array
{
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_1',
        'status' => $status,
        'product_id' => '1',
        'variant_id' => '1',
        'renews_at' => now()->addDays(20),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace, $owner, Subscription::query()->latest('id')->first()];
}

function fakeLemonSqueezyCancel(int $status = 200): void
{
    Http::fake([
        '*lemonsqueezy.com/v1/subscriptions/*' => $status === 200
            ? Http::response(['data' => ['attributes' => [
                'status' => 'cancelled',
                'product_id' => 1,
                'variant_id' => 1,
                'renews_at' => now()->addDays(20)->toIso8601String(),
                'ends_at' => now()->addDays(20)->toIso8601String(),
            ]]])
            : Http::response(['errors' => [['detail' => 'Down']]], $status),
    ]);
}

test('the owner cancels with a reason', function () {
    [$workspace, $owner, $subscription] = workspaceWithSubscriptionToCancel();
    fakeLemonSqueezyCancel();

    $this->actingAs($owner)
        ->post(route('billing.cancel'), ['reason' => 'switched', 'details' => 'Joan'])
        ->assertRedirect(route('workspaces.members'))
        ->assertSessionHas('success');

    expect($subscription->fresh()->cancelled())->toBeTrue();

    $row = SubscriptionCancellation::sole();
    expect($row->workspace_id)->toBe($workspace->id)
        ->and($row->user_id)->toBe($owner->id)
        ->and($row->reason)->toBe(CancellationReason::SWITCHED)
        ->and($row->details)->toBe('Joan')
        ->and($row->source)->toBe('in_app');
});

test('a reason is required, and Other needs a few words', function () {
    [, $owner] = workspaceWithSubscriptionToCancel();
    fakeLemonSqueezyCancel();

    $this->actingAs($owner)->post(route('billing.cancel'), [])->assertSessionHasErrors('reason');
    $this->actingAs($owner)->post(route('billing.cancel'), ['reason' => 'other'])->assertSessionHasErrors('details');

    Http::assertNothingSent();
    expect(SubscriptionCancellation::count())->toBe(0);
});

test('a member cannot cancel', function () {
    [$workspace] = workspaceWithSubscriptionToCancel();
    $member = User::factory()->active()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceRole::MEMBER]);
    fakeLemonSqueezyCancel();

    $this->actingAs($member)
        ->withSession(['selected_workspace_id' => $workspace->id])
        ->post(route('billing.cancel'), ['reason' => 'too_expensive'])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('cancelling again from a stale tab does nothing', function () {
    [, $owner] = workspaceWithSubscriptionToCancel('cancelled');
    fakeLemonSqueezyCancel();

    $this->actingAs($owner)
        ->post(route('billing.cancel'), ['reason' => 'too_expensive'])
        ->assertSessionHas('info');

    Http::assertNothingSent();
    expect(SubscriptionCancellation::count())->toBe(0);
});

test('when Lemon Squeezy fails nothing is stored', function () {
    [, $owner, $subscription] = workspaceWithSubscriptionToCancel();
    fakeLemonSqueezyCancel(500);

    $this->actingAs($owner)
        ->post(route('billing.cancel'), ['reason' => 'too_expensive'])
        ->assertSessionHas('error');

    expect($subscription->fresh()->cancelled())->toBeFalse()
        ->and(SubscriptionCancellation::count())->toBe(0);
});

test('the webhook after a cancel from the app adds nothing', function () {
    [$workspace, $owner, $subscription] = workspaceWithSubscriptionToCancel();
    fakeLemonSqueezyCancel();
    $this->actingAs($owner)->post(route('billing.cancel'), ['reason' => 'project_ended']);

    event(new SubscriptionCancelled($workspace, $subscription->fresh(), []));

    expect(SubscriptionCancellation::count())->toBe(1);
});

test('a cancel from the portal is asked about on the dashboard', function () {
    [$workspace, $owner, $subscription] = workspaceWithSubscriptionToCancel();
    $subscription->update(['status' => 'cancelled', 'ends_at' => now()->addDays(20)]);

    event(new SubscriptionCancelled($workspace, $subscription, []));

    $row = SubscriptionCancellation::sole();
    expect($row->source)->toBe('portal')->and($row->reason)->toBeNull();

    $this->actingAs($owner)->get(route('dashboard'))->assertSee('Sorry to see you go');

    $this->actingAs($owner)
        ->post(route('billing.cancellations.feedback', $row), ['reason' => 'missing_feature', 'details' => 'Outlook add-in'])
        ->assertSessionHas('success');

    expect($row->fresh()->reason)->toBe(CancellationReason::MISSING_FEATURE)
        ->and($row->fresh()->user_id)->toBe($owner->id);

    $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('Sorry to see you go');
});

test('the question can be closed', function () {
    [$workspace, $owner, $subscription] = workspaceWithSubscriptionToCancel('cancelled');
    event(new SubscriptionCancelled($workspace, $subscription, []));
    $row = SubscriptionCancellation::sole();

    $this->actingAs($owner)->post(route('billing.cancellations.dismiss', $row));

    expect($row->fresh()->dismissed_at)->not->toBeNull();
    $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('Sorry to see you go');
});

test('taking the cancellation back removes the open question', function () {
    [$workspace, , $subscription] = workspaceWithSubscriptionToCancel('cancelled');
    event(new SubscriptionCancelled($workspace, $subscription, []));

    event(new SubscriptionResumed($workspace, $subscription, []));

    expect(SubscriptionCancellation::count())->toBe(0);
});

test('someone from another workspace cannot answer for it', function () {
    [$workspace, , $subscription] = workspaceWithSubscriptionToCancel('cancelled');
    event(new SubscriptionCancelled($workspace, $subscription, []));
    $row = SubscriptionCancellation::sole();

    $outsider = User::factory()->active()->create();

    $this->actingAs($outsider)
        ->post(route('billing.cancellations.feedback', $row), ['reason' => 'too_expensive'])
        ->assertForbidden();
});

test('the workspace page offers to cancel an active subscription', function () {
    [, $owner] = workspaceWithSubscriptionToCancel();

    $this->actingAs($owner)->get(route('workspaces.members'))
        ->assertOk()
        ->assertSee('Cancel subscription')
        ->assertSee('Why are you cancelling?');
});

test('the latest reason reaches the analytics snapshot', function () {
    // The reporting tables are cloud-only and created outside the default test migrations.
    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_07_05_000001_create_billing_changes_table.php',
        '2026_08_04_000002_add_workspace_to_billing_changes.php',
        '2026_08_05_000002_make_billing_change_mrr_nullable.php',
        '2026_08_05_000003_create_analytics_workspaces_table.php',
        '2026_11_01_000003_add_profile_and_cancellation_to_analytics_workspaces.php',
    ] as $file) {
        (require database_path("migrations/{$file}"))->up();
    }

    [$workspace, $owner] = workspaceWithSubscriptionToCancel();
    fakeLemonSqueezyCancel();
    $this->actingAs($owner)->post(route('billing.cancel'), ['reason' => 'too_expensive']);

    $this->artisan('app:refresh-analytics')->assertSuccessful();

    $row = DB::table('analytics_workspaces')->where('workspace_id', $workspace->id)->first();
    expect($row->last_cancellation_reason)->toBe('too_expensive');
});
