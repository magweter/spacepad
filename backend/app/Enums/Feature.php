<?php

namespace App\Enums;

/**
 * What a workspace needs, as answered in onboarding. Several can be picked. Find a room is not built
 * yet: the answers tell us whether to build it next.
 */
enum Feature: string
{
    // Not offered any more (see offered()); kept so earlier answers still read.
    case ROOM_DISPLAYS = 'room_displays';
    case BOOKING = 'booking';
    case CHECK_IN = 'check_in';
    case BOARD = 'board';
    case LIVE_VIEW = 'live_view';
    case BOOK_FROM_DESK = 'book_from_desk';
    case FIND_A_ROOM = 'find_a_room';
    case FLOOR_PLAN = 'floor_plan';
    case INSIGHTS = 'insights';
    case BRANDING = 'branding';

    public function label(): string
    {
        return match ($this) {
            self::ROOM_DISPLAYS => 'Room displays',
            self::BOOKING => 'Book from the display',
            self::CHECK_IN => 'Check-in',
            self::BOARD => 'Overview board',
            self::LIVE_VIEW => 'Live view',
            self::BOOK_FROM_DESK => 'Book from your desk',
            self::FIND_A_ROOM => 'Find a room',
            self::FLOOR_PLAN => 'Floor plan',
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
            self::LIVE_VIEW => 'Every room at a glance',
            self::BOOK_FROM_DESK => 'Reserve a room in the browser',
            self::FIND_A_ROOM => 'A free room from your phone',
            self::FLOOR_PLAN => 'Rooms on a map of the building',
            self::INSIGHTS => 'How rooms are used',
            self::BRANDING => 'Logo, colours, photos',
        };
    }

    /**
     * Whether onboarding offers it as a choice: six at most, so the question stays quick. The
     * others stay for earlier answers.
     */
    public function offered(): bool
    {
        return in_array($this, [self::BOOKING, self::CHECK_IN, self::BOARD, self::LIVE_VIEW, self::FIND_A_ROOM, self::BRANDING], true);
    }
}
