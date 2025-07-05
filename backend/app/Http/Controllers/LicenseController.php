<?php

namespace App\Http\Controllers;

use App\Data\LicenseData;
use App\Http\Requests\ActivateLicenseRequest;
use App\Services\InstanceService;
use Illuminate\Support\Facades\Http;

class LicenseController extends Controller
{
    public function __construct(
        protected InstanceService $instanceService
    ) {}

    public function validateLicense(ActivateLicenseRequest $request)
    {
        try {
            // Get instance data
            $instanceData = $this->instanceService->getInstanceData();

            // Send validation request to the license server
            $response = Http::acceptJson()->post(config('settings.license_server') . '/api/v1/instances/activate', [
                'instance_key' => $instanceData->instanceKey,
                'license_key' => $request['license_key'],
            ]);

            if ($response->notFound()) {
                return back()->withErrors([
                    'license_key' => 'License key was not found.',
                ]);
            }

            if ($response->failed()) {
                return back()->withErrors([
                    'license_key' => 'Failed to validate license key. Please try again later.',
                ]);
            }

            $licenseData = LicenseData::from($response->json()['data']);
            if (! $licenseData->valid) {
                return back()->withErrors([
                    'license_key' => 'License key was invalid or has been used before.',
                ]);
            }

            $activated = $this->instanceService->updateLicense($licenseData);
            if (! $activated) {
                return back()->withErrors([
                    'license_key' => 'Instance could not be activated.',
                ]);
            }

            return back()->with('success', 'Thank you for supporting Spacepad! Your license key was validated successfully. Enjoy using the Pro features.');
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'license_key' => 'An error occurred while validating the license key. Please try again later.',
            ]);
        }
    }
}
