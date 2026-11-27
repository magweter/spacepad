<?php

namespace App\Enums;

/**
 * How many rooms a workspace wants on a display. The ranges of the onboarding design.
 *
 * The bounds are stored next to the answer in the reporting snapshot, so a later change to
 * the ranges still compares with what was answered before.
 */
enum RoomsEstimate: string
{
    case ONE = '1';
    case TWO_TO_FIVE = '2-5';
    case SIX_TO_TWENTY_FIVE = '6-25';
    case MORE_THAN_TWENTY_FIVE = '25+';

    public function label(): string
    {
        return match ($this) {
            self::ONE => '1',
            self::TWO_TO_FIVE => '2 to 5',
            self::SIX_TO_TWENTY_FIVE => '6 to 25',
            self::MORE_THAN_TWENTY_FIVE => 'More than 25',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ONE => 'Try it on one room',
            self::TWO_TO_FIVE => 'A small office',
            self::SIX_TO_TWENTY_FIVE => 'A floor or a building',
            self::MORE_THAN_TWENTY_FIVE => 'Several sites',
        };
    }

    public function min(): int
    {
        return match ($this) {
            self::ONE => 1,
            self::TWO_TO_FIVE => 2,
            self::SIX_TO_TWENTY_FIVE => 6,
            self::MORE_THAN_TWENTY_FIVE => 26,
        };
    }

    /**
     * Null for the open-ended range.
     */
    public function max(): ?int
    {
        return match ($this) {
            self::ONE => 1,
            self::TWO_TO_FIVE => 5,
            self::SIX_TO_TWENTY_FIVE => 25,
            self::MORE_THAN_TWENTY_FIVE => null,
        };
    }
}
