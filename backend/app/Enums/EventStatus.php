<?php

namespace App\Enums;

enum EventStatus: string
{
    case CONFIRMED = 'confirmed';
    case TENTATIVE = 'tentative';
    case CANCELLED = 'cancelled';
}
