<?php

namespace App\Services;

use App\Data\UserData;
use App\Enums\Provider;
use App\Models\CalDAVAccount;
use App\Models\GoogleAccount;
use App\Models\Instance;
use App\Models\OutlookAccount;
use App\Models\User;
use App\Data\InstanceData;
use App\Data\AccountData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstanceService
{
    private const STORAGE_KEY = 'instance_data';
    private const HASH_SALT = 'spacepad_instance_';

    public function generateInstanceId(): string
    {
        $hostname = gethostname();
        $appKey = config('app.key');

        return sha1($hostname . $appKey);
    }

    public function storeInstanceVariable(string $key, mixed $value): bool
    {
        try {
            $instanceId = $this->generateInstanceId();
            $data = $this->getStoredData();

            // Add timestamp and hash for tamper detection
            $data[$key] = [
                'value' => Crypt::encryptString($value),
                'timestamp' => now()->timestamp,
                'hash' => $this->generateDataHash($key, $value)
            ];

            return Storage::put(
                $this->getStoragePath($instanceId),
                Crypt::encryptString(json_encode($data))
            );
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public function getInstanceVariable(string $key, mixed $default = null): mixed
    {
        try {
            $data = $this->getStoredData();

            if (!isset($data[$key])) {
                return $default;
            }

            $stored = $data[$key];
            $decryptedValue = Crypt::decryptString($stored['value']);

            // Verify hash to detect tampering
            if ($this->generateDataHash($key, $decryptedValue) !== $stored['hash']) {
                return $default;
            }

            return $decryptedValue;
        } catch (\Exception $e) {
            report($e);
            return $default;
        }
    }

    private function getStoredData(): array
    {
        try {
            $instanceId = $this->generateInstanceId();
            $path = $this->getStoragePath($instanceId);

            if (!Storage::exists($path)) {
                return [];
            }

            $encrypted = Storage::get($path);
            return json_decode(Crypt::decryptString($encrypted), true) ?? [];
        } catch (\Exception $e) {
            report($e);
            return [];
        }
    }

    private function getStoragePath(string $instanceId): string
    {
        return self::STORAGE_KEY . '/' . Str::slug($instanceId) . '.dat';
    }

    private function generateDataHash(string $key, mixed $value): string
    {
        return hash_hmac(
            'sha256',
            $key . $value,
            config('app.key') . self::HASH_SALT
        );
    }

    public function getInstanceData(): InstanceData
    {
        $instanceId = $this->generateInstanceId();

        $accounts = collect([
            ...$this->getGoogleAccountUsage(),
            ...$this->getOutlookAccountUsage(),
            ...$this->getCalDAVAccountUsage(),
        ]);

        $users = User::with('displays', 'rooms')
            ->get()
            ->map(function ($user) {
                return UserData::from([
                    'email' => $user->email,
                    'status' => $user->status,
                    'numDisplays' => $user->displays->count(),
                    'numRooms' => $user->rooms->filter(function ($room) use ($user) {
                        return $user->displays->pluck('calendar_id')->contains($room->calendar_id);
                    })->count(),
                    'usageType' => $user->usage_type?->value,
                    'isUnlimited' => $user->is_unlimited,
                    'termsAcceptedAt' => $user->terms_accepted_at,
                ]);
            });

        return InstanceData::from([
            'instanceId' => $instanceId,
            'licenseKey' => $this->getInstanceVariable('license_key'),
            'version' => config('settings.version'),
            'accounts' => $accounts->toArray(),
            'users' => $users->toArray(),
        ]);
    }

    public function validateInstance(Instance $instance): bool
    {
        if (!$instance->isActive()) {
            return false;
        }

        if ($instance->isOverLimit()) {
            return false;
        }

        return true;
    }

    private function getGoogleAccountUsage(): Collection
    {
        return GoogleAccount::all()->map(function ($account) {
            $isBusiness = !empty($account->hosted_domain);

            return AccountData::from([
                'email' => $account->email,
                'status' => $account->status?->value,
                'provider' => Provider::GOOGLE,
                'isBusiness' => $isBusiness,
            ]);
        });
    }

    private function getOutlookAccountUsage(): Collection
    {
        return OutlookAccount::all()->map(function ($account) {
            $isBusiness = !empty($account->tenant_id);

            return AccountData::from([
                'email' => $account->email,
                'status' => $account->status?->value,
                'provider' => Provider::OUTLOOK,
                'isBusiness' => $isBusiness,
            ]);
        });
    }

    private function getCalDAVAccountUsage(): Collection
    {
        return CalDAVAccount::all()->map(function ($account) {
            return AccountData::from([
                'email' => $account->email,
                'status' => $account->status?->value,
                'provider' => Provider::CALDAV,
            ]);
        });
    }
}
