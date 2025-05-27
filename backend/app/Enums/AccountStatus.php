<?php

namespace App\Enums;

enum AccountStatus: string
{
    case CONNECTED = 'connected';
    case ERROR = 'error';

    public function label(): string
    {
        return match($this) {
            self::CONNECTED => 'Connected',
            self::ERROR => 'Error - needs re-authentication',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CONNECTED => 'green',
            self::ERROR => 'red',
        };
    }
} 