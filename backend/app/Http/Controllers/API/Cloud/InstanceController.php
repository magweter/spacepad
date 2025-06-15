<?php

namespace App\Http\Controllers\API\Cloud;

use App\Data\LicenseData;
use App\Http\Controllers\API\ApiController;
use App\Http\Requests\API\ValidateInstanceRequest;
use App\Http\Requests\InstanceHeartbeatRequest;
use App\Models\Instance;
use App\Services\Cloud\LicenseService;
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
        Instance::updateOrCreate(
            ['instance_key' => $request['instance_key']],
            [
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
            ]
        );

        return $this->success(
            message: 'Heartbeat received'
        );
    }

    public function validateInstance(ValidateInstanceRequest $request): JsonResponse
    {
        $instance = Instance::updateOrCreate(
            ['instance_key' => $request['instance_key']],
            [
                'instance_key' => $request['instance_key'],
                'last_validated_at' => now(),
            ]
        );

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
