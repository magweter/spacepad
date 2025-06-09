<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\InstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstanceController extends Controller
{
    public function __construct(
        protected InstanceService $instanceService
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $this->instanceService->getInstanceData();
        
        $instance = Instance::updateOrCreate(
            ['instance_id' => $data['instance_id']],
            [
                'license_key' => $data['license_key'],
                'num_displays' => $data['num_displays'],
                'email_domain' => $data['email_domain'],
                'calendar_provider' => $data['calendar_provider'],
                'version' => $data['version'],
                'last_heartbeat_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Heartbeat received',
        ]);
    }
} 