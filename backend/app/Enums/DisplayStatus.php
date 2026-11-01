<?php

namespace App\Enums;

enum DisplayStatus: string
{
    case READY = 'ready';
    case ACTIVE = 'active';
    case DEACTIVATED = 'deactivated';
    case ERROR = 'error';
    // Waiting for Pro: the free plan has one active display per workspace.
    case REQUIRES_PRO = 'requires_pro';

    public function label(): string
    {
        return match ($this) {
            self::READY => 'Ready',
            self::ACTIVE => 'Active',
            self::DEACTIVATED => 'Deactivated',
            self::ERROR => 'Error',
            self::REQUIRES_PRO => 'Requires Pro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::READY => 'blue',
            self::ACTIVE => 'green',
            self::DEACTIVATED => 'gray',
            self::ERROR => 'red',
            self::REQUIRES_PRO => 'amber',
        };
    }
}
