<?php

namespace App\Console\Commands;

use App\Data\LicenseData;
use App\Services\InstanceService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ValidateLicense extends Command
{
    protected $signature = 'spacepad:validate';
    protected $description = 'Send a validate request to the Spacepad server';

    /**
     * @throws ConnectionException
     */
    public function handle(InstanceService $instanceService): int
    {
        $data = $instanceService->getInstanceData();

        $response = Http::acceptJson()->post(config('settings.license_server') . '/api/v1/instances/validate', $data);
        if ($response->successful()) {
            $this->info('Validation successfully');

            $licenseData = LicenseData::from($response->json()['data']);
            $instanceService->updateLicense($licenseData);

            return self::SUCCESS;
        }

        $this->error('Failed to validate: ' . $response->body());
        return self::FAILURE;
    }
}
