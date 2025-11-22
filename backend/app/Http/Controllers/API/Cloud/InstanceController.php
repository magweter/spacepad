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

    public function heartbeat(InstanceHeartbeatRequest $request): JsonResponse
    {
        // First, try to find an existing instance with the same instance_key
        $existingInstance = Instance::query()
            ->where('instance_key', $request['instance_key'])
            ->latest();
        
        // Second, try to find an existing instance with the same user data by comparing JSON strings directly
        // Direct JSON comparison works for both SQLite (TEXT) and MySQL (JSON type)
        $existingInstance = $existingInstance ?? Instance::query()
            ->whereRaw('users = ?', [$request['users']])
            ->latest();

        $instanceData = [
            'instance_key' => $request['instance_key'],
            'license_key' => $request['license_key'],
            'license_valid' => $request['license_valid'],
            'license_expires_at' => $request['license_expires_at'],
            'is_self_hosted' => $request['is_self_hosted'],
            'displays_count' => $request['displays_count'],
            'rooms_count' => $request['rooms_count'],
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
