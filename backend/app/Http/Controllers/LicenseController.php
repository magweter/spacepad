<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\InstanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseController extends Controller
{
    protected $instanceService;

    public function __construct(InstanceService $instanceService)
    {
        $this->instanceService = $instanceService;
    }

    public function validate(Request $request)
    {
        $request->validate([
            'license_key' => ['required', 'string', 'regex:/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/'],
        ]);

        try {
            // Get instance data
            $instanceData = $this->instanceService->getInstanceData();
            
            // Send validation request to Spacepad server
            $response = Http::post(config('settings.spacepad_api_url') . '/api/v1/licenses/validate', [
                'instance_id' => $instanceData['instance_id'],
                'license_key' => $request->license_key,
            ]);

            if (!$response->successful()) {
                Log::error('License validation failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                
                return back()->withErrors([
                    'license_key' => 'Failed to validate license key. Please try again later.',
                ]);
            }

            $data = $response->json();

            if (!$data['valid']) {
                return back()->withErrors([
                    'license_key' => $data['message'] ?? 'Invalid license key.',
                ]);
            }

            // Update instance with license key
            Instance::updateOrCreate(
                ['instance_id' => $instanceData['instance_id']],
                [
                    'license_key' => $request->license_key,
                    'last_heartbeat_at' => now(),
                ]
            );

            return back()->with('success', 'License key validated successfully!');
        } catch (\Exception $e) {
            Log::error('License validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'license_key' => 'An error occurred while validating the license key. Please try again later.',
            ]);
        }
    }
} 