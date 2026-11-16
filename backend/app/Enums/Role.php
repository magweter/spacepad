<?php

namespace App\Enums;

/**
 * The role of the person setting up a workspace, as answered in onboarding.
 */
enum Role: string
{
    case IT_ADMIN = 'it_admin';
    case OFFICE_MANAGER = 'office_manager';
    case FOUNDER = 'founder';
    case JUST_LOOKING = 'just_looking';

    public function label(): string
    {
        return match ($this) {
            self::IT_ADMIN => 'IT / admin',
            self::OFFICE_MANAGER => 'Office manager',
            self::FOUNDER => 'Founder / lead',
            self::JUST_LOOKING => 'Just looking',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::IT_ADMIN => 'I manage Microsoft 365 or Google',
            self::OFFICE_MANAGER => 'I run the office day to day',
            self::FOUNDER => 'Small team, I do everything',
            self::JUST_LOOKING => 'Evaluating for someone else',
        };
    }
}
