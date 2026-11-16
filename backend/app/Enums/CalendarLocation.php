<?php

namespace App\Enums;

/**
 * Where a workspace's room calendars live, as answered in onboarding.
 */
enum CalendarLocation: string
{
    case MICROSOFT = 'microsoft';
    case GOOGLE = 'google';
    case CALDAV = 'caldav';
    case UNSURE = 'unsure';

    public function label(): string
    {
        return match ($this) {
            self::MICROSOFT => 'Microsoft 365 / Outlook',
            self::GOOGLE => 'Google Workspace',
            self::CALDAV => 'Nextcloud or other CalDAV',
            self::UNSURE => 'Not sure / someone else manages it',
        };
    }
}
