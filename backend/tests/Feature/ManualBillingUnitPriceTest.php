<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The analytics tables (and the refresh command) are cloud-only: their migrations skip
    // creation when self-hosted, which is the default in the test env. Flip the flag and
    // create the tables so the command can run here.
    config(['settings.is_self_hosted' => false]);

    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000002_add_workspace_to_analytics_users.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_07_05_000001_create_billing_changes_table.php',
    ] as $file) {
        $migration = require database_path("migrations/{$file}");
        $migration->up();
    }
});

/**
 * Manual billing lives on the workspace: usage is measured per workspace, so that is what
 * is invoiced. The helper still returns the user, because the admin route is addressed by
 * user and applies to their billing workspace.
 */
function manuallyBilledUserWithUsage(?float $unitPrice, int $displays = 2, int $boards = 1): User
{
    $user = User::factory()->active()->create();

    $user->primaryWorkspace()->update([
        'is_manually_billed' => true,
        'manual_billing_unit_price' => $unitPrice,
        'billing_owner_user_id' => $user->id,
    ]);

    for ($i = 0; $i < $displays; $i++) {
        Display::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->primaryWorkspace()->id,
            'status' => DisplayStatus::ACTIVE,
        ]);
    }

    for ($i = 0; $i < $boards; $i++) {
        Board::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->primaryWorkspace()->id,
        ]);
    }

    return $user;
}

function analyticsMrrFor(User $user): float
{
    return (float) DB::table('analytics_users')->where('user_id', $user->id)->value('mrr_current');
}

test('the per-account unit price overrides the global default', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    // 2 displays (1x) + 1 board (2x) = 4 billable units.
    $user = manuallyBilledUserWithUsage(12.50);

    Artisan::call('app:refresh-analytics');

    expect(analyticsMrrFor($user))->toBe(50.0);
});

test('an unset per-account unit price falls back to the global default', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $user = manuallyBilledUserWithUsage(null);

    Artisan::call('app:refresh-analytics');

    expect($user->primaryWorkspace()->manual_billing_unit_price)->toBeNull()
        ->and(analyticsMrrFor($user))->toBe(20.0);
});

test('MRR is zero when neither a per-account nor a global unit price is set', function () {
    config(['settings.manual_billing_unit_price' => null]);

    $user = manuallyBilledUserWithUsage(null);

    Artisan::call('app:refresh-analytics');

    expect(analyticsMrrFor($user))->toBe(0.0);
});

test('billable usage is floored at one unit for an account with no displays or boards', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $user = manuallyBilledUserWithUsage(9.99, displays: 0, boards: 0);

    Artisan::call('app:refresh-analytics');

    expect(analyticsMrrFor($user))->toBe(9.99);
});

test('a per-account price of zero is respected instead of falling back to the global default', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $user = manuallyBilledUserWithUsage(0);

    Artisan::call('app:refresh-analytics');

    expect(analyticsMrrFor($user))->toBe(0.0);
});

test('an admin can set the per-account unit price and MRR updates immediately', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $admin = User::factory()->active()->create(['is_admin' => true]);
    $user = manuallyBilledUserWithUsage(null);

    // Baseline snapshot at the global price (4 units x $5).
    Artisan::call('app:refresh-analytics');
    expect(analyticsMrrFor($user))->toBe(20.0);

    $this->actingAs($admin)
        ->post(route('admin.users.billing', $user), [
            'is_manually_billed' => '1',
            'manual_billing_unit_price' => '12.50',
        ])
        ->assertSessionHasNoErrors();

    expect((float) $user->primaryWorkspace()->fresh()->manual_billing_unit_price)->toBe(12.50)
        ->and(analyticsMrrFor($user))->toBe(50.0);
});

test('clearing the per-account unit price falls back to the global default', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $admin = User::factory()->active()->create(['is_admin' => true]);
    $user = manuallyBilledUserWithUsage(12.50);

    // Baseline snapshot at the per-account price (4 units x $12.50).
    Artisan::call('app:refresh-analytics');
    expect(analyticsMrrFor($user))->toBe(50.0);

    $this->actingAs($admin)
        ->post(route('admin.users.billing', $user), [
            'is_manually_billed' => '1',
            'manual_billing_unit_price' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->primaryWorkspace()->fresh()->manual_billing_unit_price)->toBeNull()
        ->and(analyticsMrrFor($user))->toBe(20.0);
});

test('the admin user page renders the unit price field with the effective MRR', function () {
    config(['settings.manual_billing_unit_price' => 5]);

    $admin = User::factory()->active()->create(['is_admin' => true]);
    $user = manuallyBilledUserWithUsage(12.50);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('Monthly price per unit')
        ->assertSee('name="manual_billing_unit_price"', false)
        ->assertSee('value="12.50"', false)
        // 4 billable units at $12.50 (the hint wraps across lines in the template)
        ->assertSeeInOrder(['Effective MRR: $12.50', '4 units', '$50.00'], false);
});

test('a negative unit price is rejected', function () {
    $admin = User::factory()->active()->create(['is_admin' => true]);
    $user = manuallyBilledUserWithUsage(12.50);

    $this->actingAs($admin)
        ->post(route('admin.users.billing', $user), [
            'is_manually_billed' => '1',
            'manual_billing_unit_price' => '-1',
        ])
        ->assertSessionHasErrors('manual_billing_unit_price');

    expect((float) $user->primaryWorkspace()->fresh()->manual_billing_unit_price)->toBe(12.50);
});
