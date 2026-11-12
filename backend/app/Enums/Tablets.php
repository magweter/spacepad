<?php

namespace App\Enums;

/**
 * What the displays will run on, as answered in onboarding. Decides the pairing route shown.
 */
enum Tablets: string
{
    case IPAD = 'ipad';
    case ANDROID = 'android';
    case MIX = 'mix';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::IPAD => 'iPads',
            self::ANDROID => 'Android tablets',
            self::MIX => 'A mix',
            self::NONE => 'Nothing yet',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::IPAD => 'iOS 15 or newer',
            self::ANDROID => 'Android 8 or newer',
            self::MIX => 'Both, or not sure yet',
            self::NONE => 'We help you choose',
        };
    }
}
