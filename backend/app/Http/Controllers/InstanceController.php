<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstanceHeartbeatRequest;
use App\Models\Instance;
use App\Services\InstanceService;
use Illuminate\Http\JsonResponse;

class InstanceController extends Controller
{
    public function __construct(
        protected InstanceService $instanceService
    ) {}

    public function heartbeat(InstanceHeartbeatRequest $request): JsonResponse
    {
        Instance::updateOrCreate(
            ['instance_id' => $request['instanceId']],
            [
                'instance_id' => $request['instanceId'],
                'license_key' => $request['licenseKey'],
                'accounts' => $request['accounts'],
                'users' => $request['users'],
                'version' => $request['version'],
                'last_heartbeat_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Heartbeat received',
        ]);
    }
}
