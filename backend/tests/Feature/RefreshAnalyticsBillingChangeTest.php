<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Enums\WorkspaceRole;
use App\Models\BillingChange;
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

/**
 * Billing changes are recorded by RecordBillingChange the instant usage moves, not
 * inferred by diffing analytics snapshots. These tests therefore mostly do not run the
 * refresh command at all: creating a display *is* the event.
 *
 * The refresh command's own job is the snapshot, plus putting a price on the changes that
 * could not be priced when they happened.
 */
beforeEach(function () {
    // The analytics + billing_changes tables (and the refresh command) are cloud-only:
    // their migrations skip creation when self-hosted, which is the default in the test
    // env. Flip the flag and create the tables so the command can run here.
    config(['settings.is_self_hosted' => false]);

    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_07_05_000001_create_billing_changes_table.php',
        '2026_08_04_000002_add_workspace_to_billing_changes.php',
        '2026_08_05_000002_make_billing_change_mrr_nullable.php',
        '2026_08_05_000003_create_analytics_workspaces_table.php',
    ] as $file) {
        $migration = require database_path("migrations/{$file}");
        $migration->up();
    }
});

function makeDisplay(User $user): Display
{
    return makeDisplayIn($user->primaryWorkspace(), $user);
}

function makeDisplayIn(Workspace $workspace, User $creator): Display
{
    return Display::factory()->create([
        'user_id' => $creator->id,
        'workspace_id' => $workspace->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
}

function joinWorkspace(Workspace $workspace, User $user, WorkspaceRole $role = WorkspaceRole::MEMBER): void
{
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);
}

function analyticsRow(Workspace $workspace): ?object
{
    return DB::table('analytics_workspaces')->where('workspace_id', $workspace->id)->first();
}

test('registering records no billing change', function () {
    $user = User::factory()->active()->create();

    Artisan::call('app:refresh-analytics');

    expect(BillingChange::where('workspace_id', $user->primaryWorkspace()->id)->count())->toBe(0);
});

test('adding displays records an increase', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    makeDisplay($user);
    makeDisplay($user);

    $changes = BillingChange::where('workspace_id', $workspace->id)->orderBy('id')->get();

    expect($changes)->toHaveCount(2);
    expect($changes->last()->change_type)->toBe('increase');
    expect($changes->last()->previous_license_count)->toBe(1);
    expect($changes->last()->new_license_count)->toBe(2);
    expect($changes->last()->license_delta)->toBe(1);
});

test('removing a display records a decrease', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    $display = makeDisplay($user);
    $display->delete();

    $change = BillingChange::where('workspace_id', $workspace->id)->orderByDesc('id')->first();

    expect($change->change_type)->toBe('decrease');
    expect($change->previous_license_count)->toBe(1);
    expect($change->new_license_count)->toBe(0);
    expect($change->license_delta)->toBe(-1);
});

test('a board is weighted 2x in the licence count', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    Board::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $change = BillingChange::where('workspace_id', $workspace->id)->orderByDesc('id')->first();

    expect($change->new_boards_count)->toBe(1);
    expect($change->new_license_count)->toBe(2);
    expect($change->license_delta)->toBe(2);
});

test('a change names the workspace and its billing contact', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    makeDisplay($user);

    $change = BillingChange::where('workspace_id', $workspace->id)->first();

    expect($change->workspace_id)->toBe($workspace->id);
    expect($change->workspace_name)->toBe($workspace->name);
    expect($change->user_id)->toBe($user->id);
    expect($change->email)->toBe($user->email);
});

test('a shared workspace records its change once, not once per member', function () {
    $owner = User::factory()->active()->create();
    $colleague = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    joinWorkspace($workspace, $colleague);

    makeDisplay($owner);
    makeDisplayIn($workspace, $colleague);

    $changes = BillingChange::where('workspace_id', $workspace->id)->get();

    expect($changes)->toHaveCount(2);
    expect($changes->last()->new_license_count)->toBe(2);
    // Both are attributed to the workspace's billing contact, whoever clicked the button.
    expect($changes->pluck('user_id')->unique()->all())->toBe([$owner->id]);
});

test('handing billing to a colleague is not a licence change', function () {
    $owner = User::factory()->active()->create();
    $colleague = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    joinWorkspace($workspace, $colleague, WorkspaceRole::OWNER);

    makeDisplay($owner);
    $before = BillingChange::where('workspace_id', $workspace->id)->count();

    $workspace->update(['billing_owner_user_id' => $colleague->id]);
    Artisan::call('app:refresh-analytics');

    expect(BillingChange::where('workspace_id', $workspace->id)->count())->toBe($before);
});

test('the snapshot holds one row per workspace, with the usage counted once', function () {
    $owner = User::factory()->active()->create();
    $colleague = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    joinWorkspace($workspace, $colleague);

    makeDisplay($owner);
    makeDisplayIn($workspace, $colleague);

    Artisan::call('app:refresh-analytics');

    $rows = DB::table('analytics_workspaces')->where('workspace_id', $workspace->id)->get();

    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->displays_count)->toBe(2);
    expect((int) $rows->first()->units)->toBe(2);
    expect((int) $rows->first()->members_count)->toBe(2);
    expect($rows->first()->billing_user_id)->toBe($owner->id);
});

test('MRR for a shared workspace is not counted twice', function () {
    config(['settings.unit_price' => 5.00]);

    $owner = User::factory()->active()->create();
    $colleague = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    joinWorkspace($workspace, $colleague);
    $workspace->update(['is_manually_billed' => true]);

    makeDisplay($owner);
    makeDisplayIn($workspace, $colleague);

    Artisan::call('app:refresh-analytics');

    // Two members, two displays, one invoice of 2 x 5.00.
    expect((float) DB::table('analytics_workspaces')->sum('mrr_current'))->toBe(10.0);
});

test('a user who carries billing for two workspaces has a row for each', function () {
    $user = User::factory()->active()->create();
    $first = $user->primaryWorkspace();
    $second = Workspace::factory()->create();
    joinWorkspace($second, $user, WorkspaceRole::OWNER);

    makeDisplayIn($first, $user);
    makeDisplayIn($second, $user);
    makeDisplayIn($second, $user);

    Artisan::call('app:refresh-analytics');

    $rows = DB::table('analytics_workspaces')->where('billing_user_id', $user->id)->get();

    expect($rows)->toHaveCount(2);
    expect((int) $rows->sum('displays_count'))->toBe(3);
});

test('a manually billed change is priced the moment it happens', function () {
    config(['settings.unit_price' => 5.00]);

    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    $workspace->update(['is_manually_billed' => true]);

    makeDisplay($user);
    makeDisplay($user);

    $change = BillingChange::where('workspace_id', $workspace->id)->orderByDesc('id')->first();

    // One unit floor on the way in, so 1 -> 2 displays is 5.00 -> 10.00.
    expect((float) $change->previous_mrr)->toBe(5.0);
    expect((float) $change->new_mrr)->toBe(10.0);
    expect((float) $change->mrr_delta)->toBe(5.0);
});

test('a Lemon Squeezy change is left unpriced until an --mrr run answers', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    makeDisplay($user);

    $change = BillingChange::where('workspace_id', $workspace->id)->first();

    // Nobody has asked Lemon Squeezy yet, and inventing a figure would be worse than
    // admitting the gap.
    expect($change->new_mrr)->toBeNull();
    expect($change->mrr_delta)->toBeNull();
});

test('an --mrr run prices each outstanding change at the unit rate', function () {
    config(['lemon-squeezy.api_key' => 'test-key']);
    cache()->flush();

    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

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

    // Three displays added in a burst, all recorded before anyone asked Lemon Squeezy
    // anything, so all three are waiting to be priced.
    makeDisplay($user);
    makeDisplay($user);
    makeDisplay($user);

    expect(BillingChange::whereNull('new_mrr')->count())->toBe(3);

    // $10.00 per unit per month.
    Http::fake([
        '*/v1/subscription-items*' => Http::response(['data' => [['attributes' => ['price_id' => 77]]]]),
        '*/v1/prices/*' => Http::response([
            'data' => ['attributes' => ['unit_price' => 1000, 'renewal_interval_unit' => 'month']],
        ]),
        '*/v1/subscriptions/*' => Http::response(['data' => ['attributes' => ['status' => 'active']]]),
    ]);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    $changes = BillingChange::where('workspace_id', $workspace->id)->orderBy('id')->get();

    // Each row priced from what it actually moved to, not from the end state: the first
    // display is worth $10, not the $30 the workspace ended up at.
    expect($changes->pluck('new_mrr')->map(fn ($mrr) => (float) $mrr)->all())->toBe([10.0, 20.0, 30.0]);
    expect($changes->pluck('mrr_delta')->map(fn ($mrr) => (float) $mrr)->all())->toBe([10.0, 10.0, 10.0]);
    expect((float) analyticsRow($workspace)->mrr_current)->toBe(30.0);
});

test('a fast refresh keeps the MRR an --mrr run fetched', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();
    makeDisplay($user);

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

    Artisan::call('app:refresh-analytics');
    DB::table('analytics_workspaces')->where('workspace_id', $workspace->id)->update(['mrr_current' => 25.00]);

    // A fast run makes no API call, so it must carry the figure rather than reset it.
    Artisan::call('app:refresh-analytics');

    expect((float) analyticsRow($workspace)->mrr_current)->toBe(25.0);
});
