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
} 