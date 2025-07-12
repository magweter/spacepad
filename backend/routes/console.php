<?php

use App\Console\Commands\CleanupExpiredEvents;
use App\Console\Commands\RenewEventSubscriptions;
use App\Console\Commands\SendHeartbeat;
use App\Console\Commands\ValidateLicense;
use App\Services\InstanceService;
use Illuminate\Support\Facades\Schedule;

// Generate random minutes for scheduling (between 0-59)
$heartbeatMinute = rand(0, 59);
$validateMinute = rand(0, 59);

Schedule::command(RenewEventSubscriptions::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(SendHeartbeat::class)
    ->when(fn() => config('settings.is_self_hosted'))
    ->hourlyAt($heartbeatMinute)
    ->withoutOverlapping();

Schedule::command(ValidateLicense::class)
    ->when(fn() => config('settings.is_self_hosted') && InstanceService::hasLicense())
    ->hourlyAt($validateMinute)
    ->withoutOverlapping();

Schedule::command(CleanupExpiredEvents::class)
    ->hourly()
    ->withoutOverlapping();
