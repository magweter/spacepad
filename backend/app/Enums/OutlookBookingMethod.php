<?php

namespace App\Enums;

enum OutlookBookingMethod: string
{
    case USER_ACCOUNT = 'user_account';
    case ADMIN_CONSENT = 'admin_consent';

    public function label(): string
    {
        return match ($this) {
            self::USER_ACCOUNT => 'User account',
            self::ADMIN_CONSENT => 'Admin consent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::USER_ACCOUNT => 'Bookings appear in your connected Microsoft account\'s calendar. Your account is listed as the organizer of every booking.',
            self::ADMIN_CONSENT => 'Bookings are created directly on the room calendar using app-level permissions. Requires a one-time approval by your M365 tenant admin.',
        };
    }
}
