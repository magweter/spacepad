<?php

use App\Services\BillingPlans;
use LemonSqueezy\Laravel\Subscription;

/**
 * Which subscriptions are yearly, and whether switching between intervals is configured.
 */
beforeEach(function () {
    config([
        'settings.pro_switch_product_id' => '10',
        'settings.pro_switch_monthly_variant_id' => '11',
        'settings.pro_switch_yearly_variant_id' => '12',
        'settings.pro_yearly_variant_ids' => ['22'],
    ]);
});

function subscriptionOnVariant(string $variant): Subscription
{
    return new Subscription(['variant_id' => $variant]);
}

test('a variant from the yearly list is yearly', function () {
    expect(BillingPlans::isYearly(subscriptionOnVariant('22')))->toBeTrue();
});

test('the yearly variant of the switch product is yearly without being listed', function () {
    expect(BillingPlans::isYearly(subscriptionOnVariant('12')))->toBeTrue();
});

test('any other variant is monthly', function () {
    expect(BillingPlans::isYearly(subscriptionOnVariant('11')))->toBeFalse()
        ->and(BillingPlans::isYearly(subscriptionOnVariant('1')))->toBeFalse()
        ->and(BillingPlans::isYearly(null))->toBeFalse();
});

test('switching is available only when the switch product is fully configured', function () {
    expect(BillingPlans::canSwitch())->toBeTrue();

    config(['settings.pro_switch_monthly_variant_id' => null]);

    expect(BillingPlans::canSwitch())->toBeFalse();
});

test('the switch ids are read from config', function () {
    expect(BillingPlans::switchProductId())->toBe('10')
        ->and(BillingPlans::switchMonthlyVariantId())->toBe('11')
        ->and(BillingPlans::switchYearlyVariantId())->toBe('12');
});
