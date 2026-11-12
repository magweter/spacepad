<?php

namespace App\Enums;

use BackedEnum;

/**
 * The questions a workspace can be asked while getting started.
 *
 * The value is what is stored in onboarding_answers.question. Only ever add cases: renaming
 * one would orphan every answer already given to it.
 */
enum OnboardingQuestion: string
{
    case ROOMS = 'rooms';
    case PREVIOUS_SOLUTION = 'previous_solution';
    case ROLE = 'role';
    case CALENDAR_LOCATION = 'calendar_provider';
    case PRODUCTS = 'products';
    case LOCATIONS = 'locations';
    case TABLETS = 'tablets';
    case FEATURES = 'features';
    case HARDWARE_GUIDE = 'hardware_guide';

    /**
     * The enum that holds this question's options.
     *
     * @return class-string<BackedEnum>
     */
    public function options(): string
    {
        return match ($this) {
            self::ROOMS => RoomsEstimate::class,
            self::PREVIOUS_SOLUTION => PreviousSolution::class,
            self::ROLE => Role::class,
            self::CALENDAR_LOCATION => CalendarLocation::class,
            self::PRODUCTS => Product::class,
            self::LOCATIONS => RoomLocations::class,
            self::TABLETS => Tablets::class,
            self::FEATURES => Feature::class,
            self::HARDWARE_GUIDE => YesNo::class,
        };
    }

    /**
     * Whether more than one option can be picked. Each picked option is its own row.
     */
    public function isMultiple(): bool
    {
        return in_array($this, [self::PRODUCTS, self::FEATURES], true);
    }

    /**
     * The option that takes free text alongside it, if any.
     */
    public function optionWithText(): ?string
    {
        return match ($this) {
            self::PREVIOUS_SOLUTION => PreviousSolution::OTHER->value,
            default => null,
        };
    }
}
