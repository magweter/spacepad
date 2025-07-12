<?php

namespace App\Enums;

enum EventStatus: string
{
    case PLANNED = 'planned';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
