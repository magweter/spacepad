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
            self::IPAD => 'iPadOS 15 or newer',
            self::ANDROID => 'Android 7 or newer',
            self::MIX => 'Both, or not sure yet',
            self::NONE => 'We can help you choose',
        };
    }
}
