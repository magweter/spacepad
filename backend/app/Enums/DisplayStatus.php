<?php

namespace App\Enums;

enum DisplayStatus: string
{
    case READY = 'ready';
    case ACTIVE = 'active';
    case DEACTIVATED = 'deactivated';
    case ERROR = 'error';

    public function label(): string
    {
        return match($this) {
            self::READY => 'Ready',
            self::ACTIVE => 'Active',
            self::DEACTIVATED => 'Deactivated',
            self::ERROR => 'Error - try recreating',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::READY => 'blue',
            self::ACTIVE => 'green',
            self::DEACTIVATED => 'gray',
            self::ERROR => 'red',
        };
    }
}
