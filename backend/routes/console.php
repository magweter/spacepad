<?php

use App\Console\Commands\CheckMarketingTriggers;
use App\Console\Commands\CleanupExpiredEvents;
use App\Console\Commands\ReconcileWorkspaceUsage;
use App\Console\Commands\RefreshAnalytics;
use App\Console\Commands\RenewEventSubscriptions;
use App\Console\Commands\SendHeartbeat;
use App\Console\Commands\UpdateLemonSqueezySubscriptions;
use App\Console\Commands\ValidateLicense;
use App\Services\InstanceService;
use Illuminate\Support\Facades\Schedule;

// Generate random minutes for scheduling (between 0-59)
$heartbeatMinute = rand(0, 59);
$validateMinute = rand(0, 59);

Schedule::command(RenewEventSubscriptions::class)
    ->everyMinute()
    ->withoutOverlapping(5); // Release lock after 5 minutes if still running

Schedule::command(SendHeartbeat::class)
    ->when(fn() => config('settings.is_self_hosted'))
    ->hourlyAt($heartbeatMinute)
    ->withoutOverlapping(10); // Release lock after 10 minutes

Schedule::command(ValidateLicense::class)
    ->when(fn() => config('settings.is_self_hosted') && InstanceService::hasLicense())
    ->hourlyAt($validateMinute)
    ->withoutOverlapping(10); // Release lock after 10 minutes

Schedule::command(CleanupExpiredEvents::class)
    ->hourly()
    ->withoutOverlapping(10); // Release lock after 10 minutes

Schedule::command(UpdateLemonSqueezySubscriptions::class)
    ->when(fn() => ! config('settings.is_self_hosted'))
    ->hourly()
    ->withoutOverlapping(10); // Release lock after 10 minutes

Schedule::command(CheckMarketingTriggers::class)
    ->when(fn() => ! config('settings.is_self_hosted'))
    ->hourly()
    ->withoutOverlapping(10); // Release lock after 10 minutes

// The usage counters are maintained by observers on every application path. This is the
// backstop that catches whatever those cannot see: a manual SQL fix, a restored backup.
Schedule::command(ReconcileWorkspaceUsage::class, ['--fix'])
    ->dailyAt('03:20')
    ->withoutOverlapping(30);

Schedule::command(RefreshAnalytics::class)
    ->when(fn() => ! config('settings.is_self_hosted'))
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

Schedule::command(RefreshAnalytics::class, ['--mrr'])
    ->when(fn() => ! config('settings.is_self_hosted'))
    ->everyThirtyMinutes()
    ->withoutOverlapping(30);
