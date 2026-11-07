<?php

namespace App\Enums;

/**
 * How many rooms a workspace expects to show. The ranges of the onboarding design.
 *
 * The bounds are stored next to the answer in the reporting snapshot, so a later change to
 * the ranges still compares with what was answered before.
 */
enum RoomsEstimate: string
{
    case ONE_TO_THREE = '1-3';
    case FOUR_TO_FIFTEEN = '4-15';
    case SIXTEEN_TO_FIFTY = '16-50';
    case FIFTY_PLUS = '50+';

    public function label(): string
    {
        return str_replace('-', '–', $this->value);
    }

    public function min(): int
    {
        return match ($this) {
            self::ONE_TO_THREE => 1,
            self::FOUR_TO_FIFTEEN => 4,
            self::SIXTEEN_TO_FIFTY => 16,
            self::FIFTY_PLUS => 50,
        };
    }

    /**
     * Null for the open-ended range.
     */
    public function max(): ?int
    {
        return match ($this) {
            self::ONE_TO_THREE => 3,
            self::FOUR_TO_FIFTEEN => 15,
            self::SIXTEEN_TO_FIFTY => 50,
            self::FIFTY_PLUS => null,
        };
    }
}
