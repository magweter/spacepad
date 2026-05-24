<?php

namespace App\Enums;

enum RoadmapStatus: string
{
    case Considering = 'considering';
    case Planned     = 'planned';
    case Building    = 'building';
    case Shipped     = 'shipped';

    public function label(): string
    {
        return match($this) {
            self::Considering => 'Under consideration',
            self::Planned     => 'Planned',
            self::Building    => 'Building now',
            self::Shipped     => 'Shipped',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Considering => 'bg-gray-100 text-gray-600',
            self::Planned     => 'bg-blue-50 text-blue-700',
            self::Building    => 'bg-amber-50 text-amber-700',
            self::Shipped     => 'bg-green-50 text-green-700',
        };
    }

    public function sortPriority(): int
    {
        return match($this) {
            self::Building    => 0,
            self::Planned     => 1,
            self::Considering => 2,
            self::Shipped     => 3,
        };
    }
}
