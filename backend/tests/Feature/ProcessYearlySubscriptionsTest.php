<?php

use App\Enums\WorkspaceRole;
use App\Events\WorkspaceUsageChanged;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A yearly subscription is paid a year ahead. Once a month, on the day the year started,
 * extra usage is invoiced for the rest of the year; the day before renewal the quantity is
 * set to current usage without proration, and a requested switch to monthly happens then.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.pro_switch_product_id' => '10',
        'settings.pro_switch_monthly_variant_id' => '11',
        'settings.pro_switch_yearly_variant_id' => '12',
        'settings.pro_yearly_variant_ids' => [],
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    cache()->flush();
    Event::fake([WorkspaceUsageChanged::class]);
});

/**
 * Lemon Squeezy as far as this command sees it: one item per subscription with a paid
 * quantity, and a swap that answers with the subscription's new attributes.
 */
function fakeYearlyApi(int $paidQuantity = 3, int $status = 200, array $failFor = []): void
{
    Http::fake(function (Request $request) use ($paidQuantity, $status, $failFor) {
        foreach ($failFor as $fragment) {
            if (str_contains($request->url(), $fragment) && $request->method() !== 'GET') {
                return Http::response([], 500);
            }
        }

        if ($request->method() === 'GET' && preg_match('#subscriptions/(sub_\w+)$#', $request->url(), $m)) {
            return Http::response(['data' => ['attributes' => ['subscription_items' => [['id' => "item_{$m[1]}"]]]]]);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), 'subscription-items/')) {
            return Http::response(['data' => ['attributes' => ['quantity' => $paidQuantity]]]);
        }

        if ($request->method() === 'PATCH' && preg_match('#subscriptions/sub_\w+$#', $request->url())) {
            return Http::response(['data' => ['attributes' => [
                'status' => 'active', 'product_id' => 10, 'variant_id' => 11,
                'renews_at' => now()->addDay()->toIso8601String(), 'ends_at' => null, 'trial_ends_at' => null,
            ]]], $status);
        }

        return Http::response(['data' => []], $status);
    });
}

function yearlyBillingWorkspace(string $renewsAt, int $displays = 3, string $variant = '12', string $id = 'sub_y'): Workspace
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);
    Display::factory()->count($displays)->create(['workspace_id' => $workspace->id]);

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => $id,
        'status' => 'active',
        'product_id' => '10',
        'variant_id' => $variant,
        'renews_at' => Carbon::parse($renewsAt),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $workspace;
}

/**
 * @return array<int, array<string, mixed>>
 */
function quantityPatches(): array
{
    return collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $r) => $r->method() === 'PATCH' && str_contains($r->url(), 'subscription-items/'))
        ->map(fn (Request $r) => $r['data']['attributes'])
        ->values()
        ->all();
}

test('extra usage is invoiced on the monthly anniversary', function () {
    $this->travelTo(Carbon::parse('2026-12-21 04:10'));
    fakeYearlyApi(paidQuantity: 3);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 4);

    Artisan::call('app:process-yearly-subscriptions');

    expect(quantityPatches())->toBe([['quantity' => 4, 'invoice_immediately' => true]]);
});

test('lower usage on the anniversary changes nothing', function () {
    $this->travelTo(Carbon::parse('2026-12-21 04:10'));
    fakeYearlyApi(paidQuantity: 5);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 2);

    Artisan::call('app:process-yearly-subscriptions');

    expect(quantityPatches())->toBe([]);
});

test('nothing happens on an ordinary day', function () {
    $this->travelTo(Carbon::parse('2026-12-20 04:10'));
    fakeYearlyApi(paidQuantity: 1);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 4);

    Artisan::call('app:process-yearly-subscriptions');

    Http::assertNothingSent();
});

test('a 31st anniversary falls on the last day of a shorter month', function () {
    $this->travelTo(Carbon::parse('2026-11-30 04:10'));
    fakeYearlyApi(paidQuantity: 1);
    yearlyBillingWorkspace('2027-03-31 10:00', displays: 2);

    Artisan::call('app:process-yearly-subscriptions');

    expect(quantityPatches())->toBe([['quantity' => 2, 'invoice_immediately' => true]]);
});

test('the day before renewal sets the quantity without proration', function () {
    $this->travelTo(Carbon::parse('2027-10-20 04:10'));
    fakeYearlyApi(paidQuantity: 6);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 2);

    Artisan::call('app:process-yearly-subscriptions');

    expect(quantityPatches())->toBe([['quantity' => 2, 'disable_prorations' => true]]);
});

test('the renewal day itself is not an anniversary', function () {
    // The renewal has just billed the year for the quantity set the day before.
    $this->travelTo(Carbon::parse('2027-10-21 04:10'));
    fakeYearlyApi(paidQuantity: 1);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 3);

    Artisan::call('app:process-yearly-subscriptions');

    Http::assertNothingSent();
});

test('a requested switch to monthly is made the day before renewal, with the quantity set first', function () {
    $this->travelTo(Carbon::parse('2027-10-20 04:10'));
    fakeYearlyApi(paidQuantity: 6);
    $workspace = yearlyBillingWorkspace('2027-10-21 10:00', displays: 2);
    $workspace->forceFill(['switch_to_monthly_requested_at' => now()->subMonth()])->save();

    Artisan::call('app:process-yearly-subscriptions');

    // Lemon Squeezy invoices the first month the moment the variant changes, for the
    // quantity it has then, so the quantity must already be the current usage.
    $writes = collect(Http::recorded())->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $r) => $r->method() === 'PATCH')
        ->map(fn (Request $r) => str_contains($r->url(), 'subscription-items/') ? 'quantity' : 'swap')
        ->values()->all();

    expect($writes)->toBe(['quantity', 'swap']);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && str_ends_with($r->url(), 'subscriptions/sub_y')
        && $r['data']['attributes']['product_id'] === '10'
        && $r['data']['attributes']['variant_id'] === '11'
        && $r['data']['attributes']['disable_prorations'] === true);
    expect(quantityPatches())->toBe([['quantity' => 2, 'disable_prorations' => true]])
        ->and($workspace->fresh()->switch_to_monthly_requested_at)->toBeNull()
        ->and(DB::table('lemon_squeezy_subscriptions')->value('variant_id'))->toBe('11');
});

test('a failed swap keeps the request and the next workspace is still processed', function () {
    $this->travelTo(Carbon::parse('2027-10-20 04:10'));
    fakeYearlyApi(paidQuantity: 1, failFor: ['subscriptions/sub_a']);
    $failing = yearlyBillingWorkspace('2027-10-21 10:00', displays: 2, id: 'sub_a');
    $failing->forceFill(['switch_to_monthly_requested_at' => now()])->save();
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 4, id: 'sub_b');

    $exitCode = Artisan::call('app:process-yearly-subscriptions');

    expect($failing->fresh()->switch_to_monthly_requested_at)->not->toBeNull()
        ->and(quantityPatches())->toContain(['quantity' => 4, 'disable_prorations' => true])
        ->and($exitCode)->toBe(1);
});

test('monthly subscriptions are left to the usage push', function () {
    $this->travelTo(Carbon::parse('2026-12-21 04:10'));
    fakeYearlyApi(paidQuantity: 1);
    yearlyBillingWorkspace('2026-12-22 10:00', displays: 4, variant: '11');

    Artisan::call('app:process-yearly-subscriptions');

    Http::assertNothingSent();
});

test('a dry run calls nothing that changes billing', function () {
    $this->travelTo(Carbon::parse('2026-12-21 04:10'));
    fakeYearlyApi(paidQuantity: 1);
    yearlyBillingWorkspace('2027-10-21 10:00', displays: 4);

    Artisan::call('app:process-yearly-subscriptions', ['--dry-run' => true]);

    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    expect(Artisan::output())->toContain('invoice');
});

test('the switch to monthly waits for the last day of the paid year', function () {
    // The swap starts a monthly cycle and invoices it straight away, so made two days early
    // the customer pays two days twice. Only the renewal quantity is set before then.
    $this->travelTo(Carbon::parse('2027-10-19 04:10'));
    fakeYearlyApi(paidQuantity: 6);
    $workspace = yearlyBillingWorkspace('2027-10-21 10:00', displays: 2);
    $workspace->forceFill(['switch_to_monthly_requested_at' => now()->subMonth()])->save();

    Artisan::call('app:process-yearly-subscriptions');

    Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH' && str_contains($r->url(), 'subscriptions/sub_y'));
    expect(quantityPatches())->toBe([['quantity' => 2, 'disable_prorations' => true]])
        ->and($workspace->fresh()->switch_to_monthly_requested_at)->not->toBeNull();
});

test('a monthly subscription that was not cancelled after the switch is cancelled now', function () {
    $this->travelTo(Carbon::parse('2026-12-20 04:10'));
    fakeYearlyApi(paidQuantity: 3);
    $workspace = yearlyBillingWorkspace('2027-10-21 10:00', displays: 3);
    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_m',
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => '1',
        'created_at' => now()->subYear(),
        'updated_at' => now(),
    ]);

    Artisan::call('app:process-yearly-subscriptions');

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'subscriptions/sub_m'));
});
