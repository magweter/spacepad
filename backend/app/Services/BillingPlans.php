<?php

namespace App\Services;

use LemonSqueezy\Laravel\Subscription;

/**
 * Which Lemon Squeezy variants are monthly or yearly, and the product used to switch.
 *
 * Lemon Squeezy knows the interval of a variant, but asking it means an API call; the
 * variant ids are fixed, so config answers the same question for free.
 */
class BillingPlans
{
    public static function isYearly(?Subscription $subscription): bool
    {
        if ($subscription === null) {
            return false;
        }

        return in_array((string) $subscription->variant_id, self::yearlyVariantIds(), true);
    }

    /**
     * Whether the product behind a switch is configured, in both directions.
     */
    public static function canSwitch(): bool
    {
        return filled(self::switchProductId())
            && filled(self::switchMonthlyVariantId())
            && filled(self::switchYearlyVariantId());
    }

    public static function switchProductId(): string
    {
        return (string) config('settings.pro_switch_product_id');
    }

    public static function switchMonthlyVariantId(): string
    {
        return (string) config('settings.pro_switch_monthly_variant_id');
    }

    public static function switchYearlyVariantId(): string
    {
        return (string) config('settings.pro_switch_yearly_variant_id');
    }

    /**
     * @return array<int, string>
     */
    private static function yearlyVariantIds(): array
    {
        return array_values(array_filter(array_map('strval', [
            ...(array) config('settings.pro_yearly_variant_ids', []),
            self::switchYearlyVariantId(),
        ])));
    }
}
