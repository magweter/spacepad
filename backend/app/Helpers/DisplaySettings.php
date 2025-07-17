<?php

namespace App\Helpers;

use App\Models\Display;
use App\Models\DisplaySetting;

class DisplaySettings
{
    public static function getSetting(Display $display, string $key, mixed $default = null): mixed
    {
        $setting = DisplaySetting::where('display_id', $display->id)
            ->where('key', $key)
            ->first();
        
        return $setting?->value ?? $default;
    }

    public static function setSetting(Display $display, string $key, mixed $value, string $type = 'string'): bool
    {
        try {
            DisplaySetting::updateOrCreate(
                [
                    'display_id' => $display->id,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                    'type' => $type,
                ]
            );
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public static function deleteSetting(Display $display, string $key): bool
    {
        try {
            return DisplaySetting::where('display_id', $display->id)
                ->where('key', $key)
                ->delete() > 0;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public static function getAllSettings(Display $display): array
    {
        return DisplaySetting::where('display_id', $display->id)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            })
            ->toArray();
    }

    // Convenience methods for common settings
    public static function isCheckInEnabled(Display $display): bool
    {
        return self::getSetting($display, 'check_in_enabled', false);
    }

    public static function setCheckInEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'check_in_enabled', $enabled, 'boolean');
    }

    public static function isBookingEnabled(Display $display): bool
    {
        return self::getSetting($display, 'booking_enabled', false);
    }

    public static function setBookingEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'booking_enabled', $enabled, 'boolean');
    }

    public static function getCheckInMinutes(Display $display): int
    {
        return self::getSetting($display, 'check_in_minutes', 15);
    }

    public static function setCheckInMinutes(Display $display, int $minutes): bool
    {
        return self::setSetting($display, 'check_in_minutes', $minutes, 'integer');
    }

    public static function getCheckInGracePeriod(Display $display): int
    {
        return self::getSetting($display, 'check_in_grace_period', 5);
    }

    public static function setCheckInGracePeriod(Display $display, int $minutes): bool
    {
        return self::setSetting($display, 'check_in_grace_period', $minutes, 'integer');
    }

    public static function isCalendarEnabled(Display $display): bool
    {
        return self::getSetting($display, 'calendar_enabled', false);
    }

    public static function setCalendarEnabled(Display $display, bool $enabled): bool
    {
        return self::setSetting($display, 'calendar_enabled', $enabled, 'boolean');
    }

    // Customizable display state texts (shorter keys)
    public static function getAvailableText(Display $display): string
    {
        return self::getSetting($display, 'text_available', 'Available');
    }
    public static function setAvailableText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_available', $text, 'string');
    }

    public static function getTransitioningText(Display $display): string
    {
        return self::getSetting($display, 'text_transitioning', 'Transitioning');
    }
    public static function setTransitioningText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_transitioning', $text, 'string');
    }

    public static function getReservedText(Display $display): string
    {
        return self::getSetting($display, 'text_reserved', 'Reserved');
    }
    public static function setReservedText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_reserved', $text, 'string');
    }

    public static function getCheckInText(Display $display): string
    {
        return self::getSetting($display, 'text_checkin', 'Check-in');
    }
    public static function setCheckInText(Display $display, string $text): bool
    {
        return self::setSetting($display, 'text_checkin', $text, 'string');
    }

    // Toggle for showing meeting title
    public static function getShowMeetingTitle(Display $display): bool
    {
        return self::getSetting($display, 'show_meeting_title', true);
    }
    public static function setShowMeetingTitle(Display $display, bool $show): bool
    {
        return self::setSetting($display, 'show_meeting_title', $show, 'boolean');
    }
} 