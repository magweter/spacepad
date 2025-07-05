<?php

namespace App\Enums;

enum UsageType: string
{
    case BUSINESS = 'business';
    case PERSONAL = 'personal';

    public function label(): string
    {
        return match($this) {
            self::BUSINESS => 'Business',
            self::PERSONAL => 'Personal / Community',
        };
    }
}
