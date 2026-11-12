<?php

namespace App\Enums;

/**
 * What a workspace used before Spacepad: nothing, its calendar, or one of the room booking
 * products people most often come from.
 */
enum PreviousSolution: string
{
    case NOTHING = 'nothing';
    case CALENDAR_ONLY = 'calendar_only';
    case JOAN = 'joan';
    case ROBIN = 'robin';
    case TEAMS_PANELS = 'teams_panels';
    case PRONESTOR = 'pronestor';
    case APPSPACE = 'appspace';
    case EPTURA = 'eptura';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOTHING => 'Nothing yet',
            self::CALENDAR_ONLY => 'Outlook or Google only',
            self::JOAN => 'Joan',
            self::ROBIN => 'Robin',
            self::TEAMS_PANELS => 'Teams panels',
            self::PRONESTOR => 'Pronestor',
            self::APPSPACE => 'Appspace',
            self::EPTURA => 'Eptura (Condeco)',
            self::OTHER => 'Something else',
        };
    }

    /**
     * Another room booking product, as opposed to nothing or just a calendar.
     */
    public function isProduct(): bool
    {
        return ! in_array($this, [self::NOTHING, self::CALENDAR_ONLY, self::OTHER], true);
    }
}
