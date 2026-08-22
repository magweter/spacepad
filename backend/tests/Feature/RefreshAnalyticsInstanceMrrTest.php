<?php

namespace Tests\Feature;

use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Self-hosted revenue, which used to be missing from the snapshot entirely.
 *
 * Both of the calls that turned a licence key into a subscription were rejected by Lemon
 * Squeezy with "Invalid Query Parameter": /license-keys cannot be filtered by the key and
 * /subscriptions cannot be filtered by customer. Every run logged a warning and booked
 * nothing, so a paid instance sat at 0. The route is licence key -> order -> subscription.
 *
 * The second thing these fix: a self-hosted licence is a flat monthly price with quantity 1
 * at Lemon Squeezy. Multiplying it by the displays the instance reports invented revenue
 * that is never invoiced.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    foreach ([
        '2026_05_30_000001_create_analytics_tables.php',
        '2026_05_30_000003_add_subscription_columns_to_analytics_instances.php',
        '2026_08_05_000003_create_analytics_workspaces_table.php',
    ] as $file) {
        $migration = require database_path("migrations/{$file}");
        $migration->up();
    }

    // The command caches every answer it can use, which would leak between tests.
    Cache::flush();
});

/**
 * A licensed self-hosted instance, priced as one of the real plans.
 *
 * @param  array<string, mixed>  $overrides  keys: licenseStatus, subscriptionStatus, unitPrice,
 *                                           quantity, usageAggregation, interval
 */
function fakeLicensedInstance(array $overrides = [], int $displays = 4, int $boards = 0): Instance
{
    $options = array_merge([
        'licenseStatus' => 'active',
        'subscriptionStatus' => 'active',
        'unitPrice' => 1000,
        'quantity' => 1,
        'usageAggregation' => null,
        'interval' => 'month',
    ], $overrides);

    Http::fake([
        // The licence key names the order it was sold with. Lemon Squeezy answers 400 for an
        // expired key while still describing it, hence a body on every status.
        'api.lemonsqueezy.com/v1/licenses/validate' => Http::response([
            'valid' => $options['licenseStatus'] === 'active',
            'license_key' => ['status' => $options['licenseStatus']],
            'meta' => ['order_id' => 5844728, 'customer_id' => 6170303],
        ], $options['licenseStatus'] === 'active' ? 200 : 400),

        'api.lemonsqueezy.com/v1/subscriptions?filter*' => Http::response([
            'data' => [[
                'id' => '1674461',
                'attributes' => ['status' => $options['subscriptionStatus'], 'created_at' => '2026-01-11T20:18:59.000000Z'],
            ]],
        ]),

        'api.lemonsqueezy.com/v1/subscriptions/*' => Http::response([
            'data' => ['attributes' => ['status' => $options['subscriptionStatus']]],
        ]),

        'api.lemonsqueezy.com/v1/subscription-items*' => Http::response([
            'data' => [['attributes' => ['price_id' => 1363823, 'quantity' => $options['quantity']]]],
        ]),

        'api.lemonsqueezy.com/v1/prices/*' => Http::response([
            'data' => ['attributes' => [
                'unit_price' => $options['unitPrice'],
                'renewal_interval_unit' => $options['interval'],
                'usage_aggregation' => $options['usageAggregation'],
            ]],
        ]),
    ]);

    return Instance::create([
        'instance_key' => 'key-'.uniqid(),
        'license_key' => 'D0EE30C3-TEST',
        'license_valid' => true,
        'displays_count' => $displays,
        'boards_count' => $boards,
    ]);
}

function instanceRow(Instance $instance): ?object
{
    return DB::table('analytics_instances')->where('instance_id', $instance->id)->first();
}

test('an active self-hosted licence books its flat monthly price', function () {
    $instance = fakeLicensedInstance(['unitPrice' => 2000], displays: 4);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    $row = instanceRow($instance);

    // $20 a month for the licence, not $20 x 4 displays.
    expect((float) $row->mrr_current)->toBe(20.0)
        ->and((float) $row->mrr_expected)->toBe(20.0)
        ->and($row->subscription_status)->toBe('active')
        ->and($row->lemon_squeezy_id)->toBe('1674461');
});

test('an expired licence books nothing and reports the status Lemon Squeezy has', function () {
    // The local license_valid flag says otherwise, and is exactly what was believed before.
    $instance = fakeLicensedInstance([
        'licenseStatus' => 'expired',
        'subscriptionStatus' => 'expired',
    ]);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    $row = instanceRow($instance);

    expect((float) $row->mrr_current)->toBe(0.0)
        ->and((float) $row->mrr_expected)->toBe(0.0)
        ->and($row->subscription_status)->toBe('expired')
        ->and($instance->license_valid)->toBeTrue();
});

test('a trialling licence counts towards expected but not current revenue', function () {
    $instance = fakeLicensedInstance(['subscriptionStatus' => 'on_trial']);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    $row = instanceRow($instance);

    expect((float) $row->mrr_current)->toBe(0.0)
        ->and((float) $row->mrr_expected)->toBe(10.0);
});

test('a metered licence is priced on the usage the instance reports', function () {
    // 2 displays + 1 board (counts double) = 4 units, at $5.
    $instance = fakeLicensedInstance([
        'unitPrice' => 500,
        'quantity' => 0,
        'usageAggregation' => 'sum',
    ], displays: 2, boards: 1);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    expect((float) instanceRow($instance)->mrr_current)->toBe(20.0);
});

test('a yearly licence is reported as its monthly equivalent', function () {
    $instance = fakeLicensedInstance(['unitPrice' => 12000, 'interval' => 'year']);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    expect((float) instanceRow($instance)->mrr_current)->toBe(10.0);
});

test('an instance without a licence key reaches the API not at all', function () {
    Http::fake();

    $instance = Instance::create([
        'instance_key' => 'key-unlicensed',
        'license_valid' => false,
        'displays_count' => 3,
    ]);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);

    Http::assertNothingSent();

    expect((float) instanceRow($instance)->mrr_current)->toBe(0.0)
        ->and(instanceRow($instance)->subscription_status)->toBe('inactive');
});

test('a run without --mrr keeps the figures the last MRR run fetched', function () {
    $instance = fakeLicensedInstance(['unitPrice' => 2000]);

    Artisan::call('app:refresh-analytics', ['--mrr' => true]);
    expect((float) instanceRow($instance)->mrr_current)->toBe(20.0);

    Http::fake();
    Artisan::call('app:refresh-analytics');

    Http::assertNothingSent();
    expect((float) instanceRow($instance)->mrr_current)->toBe(20.0);
});
