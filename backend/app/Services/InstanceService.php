<?php

namespace App\Services;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InstanceService
{
    public function generateInstanceId(): string
    {
        $hostname = gethostname();
        $appKey = config('app.key');

        return Hash::make($hostname . $appKey);
    }

    public function getInstanceData(): array
    {
        $users = User::withCount(['displays'])->map(function ($user) {
            return [
                'email' => $user->email,
                'status' => $user->status,
                'num_displays' => $user->displays_count,
                'usage_type' => $user->usage_type,
                'is_unlimited' => $user->is_unlimited,
                'terms_accepted_at' => $user->terms_accepted_at,
                //'license_key' => $user->license_key,
            ];
        });

        $displays = Display::all()->map(function ($user) {
            return [
                'email' => $user->email,
                'status' => $user->status,
                'num_displays' => $user->displays_count,
                'usage_type' => $user->usage_type,
                'is_unlimited' => $user->is_unlimited,
                'terms_accepted_at' => $user->terms_accepted_at,
            ];
        });

        // Versie container

        return [
            'instance_id' => $this->generateInstanceId(),
            'timestamp' => now()->toIso8601String(),
            'version' => config('settings.version'),
            'users' => $users,
            'displays' => $displays,
        ];
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
}
