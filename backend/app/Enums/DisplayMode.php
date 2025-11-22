<?php

namespace App\Enums;

enum DisplayMode: string
{
    case HORIZONTAL = 'horizontal';
    case AVAILABILITY = 'availability';

    public function label(): string
    {
        return match($this) {
            self::HORIZONTAL => 'Horizontal',
            self::AVAILABILITY => 'Availability View',
        };
    }
}

