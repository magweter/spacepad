<?php

namespace App\Enums;

enum PermissionType: string
{
    case READ = 'read';
    case WRITE = 'write';

    public function label(): string
    {
        return match($this) {
            self::READ => 'Read Only',
            self::WRITE => 'Read & Write',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::READ => 'View calendar events and room availability. Cannot create or modify events.',
            self::WRITE => 'View calendar events and create new bookings. Required for ad-hoc room bookings when users book rooms directly from the tablet display.',
        };
    }
}

