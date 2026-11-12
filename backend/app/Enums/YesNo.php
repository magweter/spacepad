<?php

namespace App\Enums;

/**
 * A plain yes or no, for onboarding questions such as "send me the hardware guide".
 */
enum YesNo: string
{
    case YES = 'yes';
    case NO = 'no';

    public function label(): string
    {
        return $this === self::YES ? 'Yes' : 'No';
    }
}
