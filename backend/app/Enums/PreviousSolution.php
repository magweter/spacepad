<?php

namespace App\Enums;

/**
 * What a workspace used before Spacepad. The options of the onboarding design.
 */
enum PreviousSolution: string
{
    case NOTHING = 'nothing';
    case CALENDAR_ONLY = 'calendar_only';
    case PAPER = 'paper';
    case JOAN = 'joan';
    case TEAMS_PANELS = 'teams_panels';
    case ROBIN_OR_SUITE = 'robin_or_suite';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NOTHING => 'Nothing / walk and look',
            self::CALENDAR_ONLY => 'Outlook or Google only',
            self::PAPER => 'Paper or whiteboard',
            self::JOAN => 'Joan',
            self::TEAMS_PANELS => 'Teams panels',
            self::ROBIN_OR_SUITE => 'Robin or another suite',
            self::OTHER => 'Something else',
        };
    }
}
