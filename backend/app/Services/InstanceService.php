<?php

namespace App\Services;

use App\Data\LicenseData;
use App\Data\UserData;
use App\Models\Display;
use App\Models\Room;
use App\Models\User;
use App\Data\InstanceData;
use App\Helpers\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InstanceService
{
    private const SETTING_PREFIX = 'instance_data';

    public static function generateInstanceKey(): string
    {
        $hostname = gethostname();
        $appKey = config('app.key');

        return sha1($hostname . $appKey);
    }

    public static function hasValidLicense(): bool
    {
        $licenseKey = self::getInstanceVariable('license_key');
        $licenseValid = self::getInstanceVariable('license_valid');
        if (! $licenseKey || ! $licenseValid) {
            return false;
        }

        $expiresAt = self::getInstanceVariable('license_expires_at');
        if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
            return false;
        }

        return true;
    }

    public static function hasLicense(): bool
    {
        return self::getInstanceVariable('license_key') !== null;
    }

    public static function updateLicense(LicenseData $data): bool
    {
        return self::storeInstanceVariable('license_key', $data->licenseKey) &&
            self::storeInstanceVariable('license_valid', $data->valid) &&
            self::storeInstanceVariable('license_expires_at', $data->expiresAt);
    }

    public static function storeInstanceVariable(string $key, ?string $value): bool
    {
        try {
            $key = self::getSettingKey($key);

            if (is_null($value)) {
                return Settings::deleteSetting($key);
            }

            return Settings::setSetting($key, $value);
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public static function getInstanceVariable(string $key, mixed $default = null): mixed
    {
        try {
            return Settings::getSetting(self::getSettingKey($key), $default);
        } catch (\Exception $e) {
            report($e);
            return $default;
        }
    }

    private static function getSettingKey(string $key): string
    {
        $instanceKey = self::generateInstanceKey();
        return self::SETTING_PREFIX . '_' . $instanceKey . '_' . Str::snake($key);
    }

    public static function getInstanceData(): InstanceData
    {
        $instanceKey = self::generateInstanceKey();

        $users = User::all()->map(function ($user) {
            return new UserData(
                email: $user->email,
                usageType: $user->usage_type?->value,
                isUnlimited: $user->is_unlimited,
                termsAcceptedAt: $user->terms_accepted_at,
            );
        });

        $licenseExpiresAt = self::getInstanceVariable('license_expires_at');
        return new InstanceData(
            instanceKey: $instanceKey,
            licenseKey: self::getInstanceVariable('license_key'),
            licenseValid: self::getInstanceVariable('license_valid'),
            licenseExpiresAt: $licenseExpiresAt ? Carbon::parse($licenseExpiresAt) : null,
            isSelfHosted: config('settings.is_self_hosted'),
            displaysCount: Display::count(),
            roomsCount: Room::count(),
            version: config('settings.version'),
            users: $users->toArray()
        );
    }
}
