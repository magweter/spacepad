<?php

namespace App\Enums;

/**
 * Where a workspace's rooms are, as answered in onboarding. Several buildings or countries
 * means displays get grouped by building, and usually an Enterprise conversation.
 */
enum RoomLocations: string
{
    case ONE_LOCATION = 'one_location';
    case SEVERAL_BUILDINGS = 'several_buildings';
    case SEVERAL_COUNTRIES = 'several_countries';

    public function label(): string
    {
        return match ($this) {
            self::ONE_LOCATION => 'One location',
            self::SEVERAL_BUILDINGS => 'Several buildings',
            self::SEVERAL_COUNTRIES => 'Several countries',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ONE_LOCATION => 'One building, one address',
            self::SEVERAL_BUILDINGS => 'Floors or sites, one organisation',
            self::SEVERAL_COUNTRIES => 'Separate offices and admins',
        };
    }
}
