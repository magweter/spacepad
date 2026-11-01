<?php

namespace App\Enums;

/**
 * What a workspace used before Spacepad, as answered on the dashboard.
 */
enum PreviousSolution: string
{
    case NOTHING = 'nothing';
    case PAPER = 'paper';
    case JOAN = 'joan';
    case ROBIN = 'robin';
    case TEAMS_ROOMS = 'teams_rooms';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOTHING => 'Nothing yet',
            self::PAPER => 'Paper or a sign',
            self::JOAN => 'Joan',
            self::ROBIN => 'Robin',
            self::TEAMS_ROOMS => 'Teams Rooms panel',
            self::OTHER => 'Something else',
        };
    }
}
