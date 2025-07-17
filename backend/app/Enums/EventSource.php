<?php

namespace App\Enums;

enum EventSource
{
    const GOOGLE = 'google';
    const OUTLOOK = 'outlook';
    const CALDAV = 'caldav';
    const CUSTOM = 'custom';
}
