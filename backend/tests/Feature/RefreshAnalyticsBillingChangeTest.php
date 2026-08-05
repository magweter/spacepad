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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The analytics + billing_changes tables (and the refresh command) are cloud-only:
    // their migrations skip creation when self-hosted, which is the default in the test
    // env. Flip the flag and create the tables so the command can run here.
    config(['settings.is_self_hosted' => false]);

    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000002_add_workspace_to_analytics_users.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_07_05_000001_create_billing_changes_table.php',
        '2026_08_04_000001_scope_analytics_users_to_workspaces.php',
        '2026_08_04_000002_add_workspace_to_billing_changes.php',
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

function analyticsRowsFor(Workspace $workspace): Collection
{
    return DB::table('analytics_users')->where('workspace_id', $workspace->id)->get();
}

test('no billing change is recorded for a brand-new user on the first refresh', function () {
    $user = User::factory()->active()->create();

    Artisan::call('app:refresh-analytics');

    expect(BillingChange::count())->toBe(0);
});

test('an increase in license count records an increase change', function () {
    $user = User::factory()->active()->create();

    // Baseline snapshot (0 displays).
    Artisan::call('app:refresh-analytics');

    makeDisplay($user);
    makeDisplay($user);

    Artisan::call('app:refresh-analytics');

    $change = BillingChange::where('user_id', $user->id)->latest('detected_at')->first();

    expect($change)->not->toBeNull()
        ->and($change->change_type)->toBe('increase')
        ->and($change->previous_license_count)->toBe(0)
        ->and($change->new_license_count)->toBe(2)
        ->and($change->license_delta)->toBe(2)
        ->and($change->new_displays_count)->toBe(2);
});

test('a decrease in license count records a decrease change', function () {
    $user = User::factory()->active()->create();
    $display = makeDisplay($user);

    // Baseline snapshot (1 display).
    Artisan::call('app:refresh-analytics');

    $display->delete();

    Artisan::call('app:refresh-analytics');

    $change = BillingChange::where('user_id', $user->id)->latest('detected_at')->first();

    expect($change)->not->toBeNull()
        ->and($change->change_type)->toBe('decrease')
        ->and($change->previous_license_count)->toBe(1)
        ->and($change->new_license_count)->toBe(0)
        ->and($change->license_delta)->toBe(-1);
});

test('boards are weighted 2x in the license count', function () {
    $user = User::factory()->active()->create();

    Artisan::call('app:refresh-analytics');

    Board::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
    ]);

    Artisan::call('app:refresh-analytics');

    $change = BillingChange::where('user_id', $user->id)->latest('detected_at')->first();

    expect($change)->not->toBeNull()
        ->and($change->new_boards_count)->toBe(1)
        ->and($change->new_license_count)->toBe(2)
        ->and($change->license_delta)->toBe(2);
});

test('a shared workspace counts its usage once, not once per member', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    makeDisplayIn($workspace, $owner);
    makeDisplayIn($workspace, $owner);

    $colleague = User::factory()->active()->create();
    joinWorkspace($workspace, $colleague);

    Artisan::call('app:refresh-analytics');

    $rows = analyticsRowsFor($workspace);

    // Both members appear, but the two displays are counted once between them.
    expect($rows)->toHaveCount(2)
        ->and($rows->sum('displays_count'))->toBe(2)
        ->and($rows->where('is_billing_owner', true)->count())->toBe(1);

    expect($rows->firstWhere('user_id', $owner->id)->displays_count)->toBe(2)
        ->and($rows->firstWhere('user_id', $colleague->id)->displays_count)->toBe(0)
        ->and($rows->firstWhere('user_id', $colleague->id)->subscription_status)->toBe('member');
});

test('MRR for a shared workspace is not counted twice', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    $workspace->update(['is_manually_billed' => true, 'manual_billing_unit_price' => 5]);
    makeDisplayIn($workspace, $owner);
    makeDisplayIn($workspace, $owner);

    $colleague = User::factory()->active()->create();
    joinWorkspace($workspace, $colleague);

    Artisan::call('app:refresh-analytics');

    // 2 displays × €5, once — not €20 across two rows.
    expect((float) analyticsRowsFor($workspace)->sum('mrr_current'))->toBe(10.0);
});

test('a user who carries billing for two workspaces gets a row for each', function () {
    $user = User::factory()->active()->create();
    $own = $user->primaryWorkspace();
    makeDisplayIn($own, $user);

    $second = Workspace::factory()->create(['billing_owner_user_id' => $user->id]);
    joinWorkspace($second, $user, WorkspaceRole::OWNER);
    makeDisplayIn($second, $user);
    makeDisplayIn($second, $user);

    Artisan::call('app:refresh-analytics');

    $rows = DB::table('analytics_users')->where('user_id', $user->id)->get();

    // Neither workspace is dropped: three displays across two rows.
    expect($rows)->toHaveCount(2)
        ->and($rows->sum('displays_count'))->toBe(3)
        ->and($rows->where('is_billing_owner', true)->count())->toBe(2);
});

test('handing billing to a colleague is not reported as a licence change', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    makeDisplayIn($workspace, $owner);
    makeDisplayIn($workspace, $owner);

    $colleague = User::factory()->active()->create();
    joinWorkspace($workspace, $colleague, WorkspaceRole::OWNER);

    // Baseline snapshot with the original owner carrying the billing.
    Artisan::call('app:refresh-analytics');

    $workspace->update(['billing_owner_user_id' => $colleague->id]);

    Artisan::call('app:refresh-analytics');

    // Usage never moved, so neither a decrease for one nor an increase for the other.
    expect(BillingChange::where('workspace_id', $workspace->id)->count())->toBe(0)
        ->and(analyticsRowsFor($workspace)->firstWhere('user_id', $colleague->id)->displays_count)->toBe(2)
        ->and(analyticsRowsFor($workspace)->firstWhere('user_id', $owner->id)->displays_count)->toBe(0);
});

test('a licence change records the workspace it belongs to', function () {
    $user = User::factory()->active()->create();
    $workspace = $user->primaryWorkspace();

    Artisan::call('app:refresh-analytics');

    makeDisplayIn($workspace, $user);

    Artisan::call('app:refresh-analytics');

    $change = BillingChange::latest('detected_at')->first();

    expect($change)->not->toBeNull()
        ->and($change->workspace_id)->toBe($workspace->id)
        ->and($change->workspace_name)->toBe($workspace->name)
        ->and($change->user_id)->toBe($user->id);
});

test('a fast refresh keeps the MRR an --mrr run fetched, even before any row is flagged', function () {
    $owner = User::factory()->active()->create();
    $workspace = $owner->primaryWorkspace();
    makeDisplayIn($workspace, $owner);

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

    // The state left behind by the migration: a snapshot from before this table became
    // per-membership, so it carries MRR but no is_billing_owner flag yet.
    DB::table('analytics_users')->insert([
        'user_id' => $owner->id,
        'workspace_id' => $workspace->id,
        'workspace_name' => $workspace->name,
        'is_billing_owner' => false,
        'email' => $owner->email,
        'name' => $owner->name,
        'displays_count' => 1,
        'boards_count' => 0,
        'subscription_status' => 'active',
        'mrr_current' => 25,
        'mrr_expected' => 25,
        'refreshed_at' => now()->subMinutes(5),
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    // A fast run reaches no API, so the figure can only survive by being carried over.
    Artisan::call('app:refresh-analytics');

    $row = analyticsRowsFor($workspace)->firstWhere('is_billing_owner', true);

    expect($row)->not->toBeNull()
        ->and((float) $row->mrr_current)->toBe(25.0)
        ->and((float) $row->mrr_expected)->toBe(25.0);
});
