<?php

namespace App\Http\Controllers\API\Cloud;

use App\Data\LicenseData;
use App\Http\Controllers\API\ApiController;
use App\Http\Requests\API\InstanceHeartbeatRequest;
use App\Http\Requests\API\ValidateInstanceRequest;
use App\Infrastructure\Cloud\LicenseService;
use App\Models\Instance;
use App\Services\InstanceService;
use Illuminate\Http\JsonResponse;
use LemonSqueezy\Laravel\Exceptions\LemonSqueezyApiError;
use LemonSqueezy\Laravel\Exceptions\LicenseKeyNotFound;

class InstanceController extends ApiController
{
    public function __construct(
        protected InstanceService $instanceService
    ) {}

    /**
     * Pseudonymize an IP address using HMAC-SHA256 with APP_KEY.
     * This allows consistent pseudonymization for audit purposes while protecting PII.
     * Returns first 16 characters of the hash for readability.
     *
     * @param string $ip
     * @return string
     */
    private function pseudonymizeIp(string $ip): string
    {
        $key = config('app.key');
        $hash = hash_hmac('sha256', $ip, $key);
        return substr($hash, 0, 16);
    }

    public function heartbeat(InstanceHeartbeatRequest $request): JsonResponse
    {
        // Security: Log instance heartbeat for audit trail
        logger()->info('Instance heartbeat received', [
            'instance_key' => substr($request['instance_key'], 0, 8) . '...', // Log partial key only
            'ip_hash' => $this->pseudonymizeIp(request()->ip()),
            'version' => $request['version'],
        ]);

        // First, try to find an existing instance with the same instance_key
        $existingInstance = Instance::query()
            ->where('instance_key', $request['instance_key'])
            ->latest()
            ->first();
        
        // Second, try to find an existing instance with the same user data by comparing JSON strings directly
        // Direct JSON comparison works for both SQLite (TEXT) and MySQL (JSON type)
        // Always convert users to JSON string for comparison, regardless of input type
        $usersValue = $request['users'];
        $usersJson = is_string($usersValue) 
            ? $usersValue 
            : json_encode($usersValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Ensure it's always a string (not array) for database comparison
        $usersJson = (string) $usersJson;
        
        $existingInstance = $existingInstance ?? Instance::query()
            ->whereRaw('users = ?', [$usersJson])
            ->latest()
            ->first();

        $instanceData = [
            'instance_key' => $request['instance_key'],
            'license_key' => $request['license_key'],
            'license_valid' => $request['license_valid'],
            'license_expires_at' => $request['license_expires_at'],
            'is_self_hosted' => $request['is_self_hosted'],
            'displays_count' => $request['displays_count'],
            'rooms_count' => $request['rooms_count'],
            'boards_count' => $request['boards_count'] ?? null,
            'users' => $request['users'],
            'version' => $request['version'],
            'last_heartbeat_at' => now(),
        ];

        // If found, update that instance instead of creating a new one
        if ($existingInstance !== null) {
            $existingInstance->update($instanceData);
        } else {
            // No existing instance, create a new instance
            Instance::create($instanceData);
        }

        return $this->success(
            message: 'Heartbeat received'
        );
    }

    public function validateInstance(ValidateInstanceRequest $request): JsonResponse
    {
        // Security: Log instance validation for audit trail
        logger()->info('Instance validation received', [
            'instance_key' => substr($request['instance_key'], 0, 8) . '...', // Log partial key only
            'ip_hash' => $this->pseudonymizeIp(request()->ip()),
        ]);

        // Fetch current instance and update last validated at timestamp
        $instance = Instance::updateOrCreate(
            ['instance_key' => $request['instance_key']],
            [
                'instance_key' => $request['instance_key'],
                'last_validated_at' => now(),
            ]
        );

        // Return current instance data to sync license data
        return $this->success(
            message: 'Instance successfully validated',
            data: LicenseData::fromModel($instance)
        );
    }

    public function activate(ValidateInstanceRequest $request): JsonResponse
    {
        // Security: Log instance activation for audit trail
        logger()->info('Instance activation attempt', [
            'instance_key' => substr($request['instance_key'], 0, 8) . '...', // Log partial key only
            'ip_hash' => $this->pseudonymizeIp(request()->ip()),
            'has_license_key' => !empty($request['license_key']),
        ]);

        $instance = Instance::updateOrCreate(
            ['instance_key' => $request['instance_key']],
            [
                'instance_key' => $request['instance_key'],
                'last_heartbeat_at' => now(),
            ]
        );

        try {
            LicenseService::activateLicense([
                'license_key' => $request['license_key'],
                'instance_name' => $instance->id,
            ]);

            // Update instance with license key
            $instance->update([
                'license_key' => $request['license_key'],
                'license_valid' => true,
            ]);

            return $this->success(
                message: 'Instance activated successfully',
                data: LicenseData::fromModel($instance)
            );
        } catch (LicenseKeyNotFound|LemonSqueezyApiError $e) {
            return $this->error(
                message: 'License key not found',
                code: 404
            );
        } catch (\Exception $e) {
            report($e);
            return $this->error(
                message: 'Instance could not be activated',
                code: 500
            );
        }
    }
}
