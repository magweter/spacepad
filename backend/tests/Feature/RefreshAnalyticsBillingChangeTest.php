<?php

namespace Tests\Feature;

use App\Enums\DisplayStatus;
use App\Models\BillingChange;
use App\Models\Board;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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
    ] as $file) {
        $migration = require database_path("migrations/{$file}");
        $migration->up();
    }
});

function makeDisplay(User $user): Display
{
    return Display::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'status' => DisplayStatus::ACTIVE,
    ]);
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
