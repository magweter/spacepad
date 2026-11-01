<?php

namespace App\Enums;

/**
 * How many rooms a workspace expects to show, as answered on the dashboard.
 */
enum RoomsEstimate: string
{
    case ONE = '1';
    case TWO_TO_FIVE = '2-5';
    case SIX_TO_TWENTY = '6-20';
    case TWENTY_PLUS = '20+';

    public function label(): string
    {
        return $this->value;
    }
}
