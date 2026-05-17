<?php

namespace App\Enums;

enum GoogleBookingMethod: string
{
    case SERVICE_ACCOUNT = 'service_account';
    case USER_ACCOUNT = 'user_account';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_ACCOUNT => 'Service account',
            self::USER_ACCOUNT => 'User account',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SERVICE_ACCOUNT => 'Bookings are created using a dedicated Google service account. The room calendar is updated directly without appearing in any personal mailbox.',
            self::USER_ACCOUNT => 'Bookings appear in your connected Google account\'s calendar. Your account is listed as the organizer of every booking.',
        };
    }
}
