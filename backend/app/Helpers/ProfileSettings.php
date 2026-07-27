<?php

namespace App\Helpers;

use App\Models\DisplayProfile;
use App\Models\DisplayProfileSetting;

/**
 * Storage helper for the key/value settings that make up a Display Profile.
 * Mirrors the storage side of {@see DisplaySettings}; the read/resolution side
 * for displays (profile fallback + per-display override) lives in DisplaySettings.
 */
class ProfileSettings
{
    public static function get(DisplayProfile $profile, string $key, mixed $default = null): mixed
    {
        if ($profile->relationLoaded('settings')) {
            $setting = $profile->settings->firstWhere('key', $key);

            return $setting ? $setting->value : $default;
        }

        $setting = DisplayProfileSetting::where('display_profile_id', $profile->id)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(DisplayProfile $profile, string $key, mixed $value, string $type = 'string'): bool
    {
        try {
            DisplayProfileSetting::updateOrCreate(
                [
                    'display_profile_id' => $profile->id,
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

    public static function delete(DisplayProfile $profile, string $key): bool
    {
        try {
            return DisplayProfileSetting::where('display_profile_id', $profile->id)
                ->where('key', $key)
                ->delete() > 0;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Return all of the profile's settings as a key => value map.
     */
    public static function all(DisplayProfile $profile): array
    {
        $settings = $profile->relationLoaded('settings')
            ? $profile->settings
            : DisplayProfileSetting::where('display_profile_id', $profile->id)->get();

        return $settings->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])->toArray();
    }
}
