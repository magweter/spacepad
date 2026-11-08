<?php

namespace App\Enums;

/**
 * Where a workspace wants Spacepad to show up, as answered in onboarding.
 */
enum Product: string
{
    case DISPLAY = 'display';
    case BOARD = 'board';
    case CONSOLE = 'console';

    public function tag(): string
    {
        return match ($this) {
            self::DISPLAY => 'Display',
            self::BOARD => 'Board · Pro',
            self::CONSOLE => 'Console · coming 2027',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DISPLAY => 'Wall tablet at the door',
            self::BOARD => 'Overview on a TV',
            self::CONSOLE => 'Controls on the table',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DISPLAY => 'Live status, booking and check-in next to every meeting room.',
            self::BOARD => 'Every room on one screen for lobbies, receptions and lift areas.',
            self::CONSOLE => 'Extend, end or check in from inside the room.',
        };
    }
}
