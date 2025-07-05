<?php

namespace App\Helpers;

use App\Models\Setting;

class Settings
{
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    public static function setSetting(string $key, mixed $value, string $type = 'string'): bool
    {
        try {
            Setting::updateOrCreate(
                ['key' => $key],
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

    public static function deleteSetting(string $key): bool
    {
        try {
            return Setting::where('key', $key)->delete() > 0;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public static function getAllSettings(): array
    {
        return Setting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        })->toArray();
    }
}
