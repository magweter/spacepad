<?php

namespace App\Console\Commands;

use App\Services\InstanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendHeartbeat extends Command
{
    protected $signature = 'spacepad:heartbeat';
    protected $description = 'Send a heartbeat to the Spacepad server';

    public function handle(InstanceService $instanceService): int
    {
        $data = $instanceService->getInstanceData();
        $response = Http::post(config('settings.license_server') . '/api/v1/instances/heartbeat', $data);

        if ($response->successful()) {
            $this->info('Heartbeat sent successfully');
            return self::SUCCESS;
        }

        $this->error('Failed to send heartbeat: ' . $response->body());
        return self::FAILURE;
    }
}
