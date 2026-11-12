<?php

namespace App\Enums;

/**
 * Which features matter to a workspace, as answered in onboarding. Several can be picked.
 */
enum Feature: string
{
    case ROOM_DISPLAYS = 'room_displays';
    case BOOKING = 'booking';
    case CHECK_IN = 'check_in';
    case BOARD = 'board';
    case INSIGHTS = 'insights';
    case BRANDING = 'branding';

    public function label(): string
    {
        return match ($this) {
            self::ROOM_DISPLAYS => 'Room displays',
            self::BOOKING => 'Book from the display',
            self::CHECK_IN => 'Check-in',
            self::BOARD => 'Overview board',
            self::INSIGHTS => 'Insights',
            self::BRANDING => 'Own branding',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ROOM_DISPLAYS => 'Status at the door',
            self::BOOKING => 'Walk up and reserve',
            self::CHECK_IN => 'Free up no-shows',
            self::BOARD => 'All rooms on one screen',
            self::INSIGHTS => 'How rooms are used',
            self::BRANDING => 'Logo, colours, photos',
        };
    }
}
