<?php

use App\Console\Commands\RenewEventSubscriptions;
use App\Console\Commands\SendHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::command(RenewEventSubscriptions::class)->everyMinute();
Schedule::command(SendHeartbeat::class)
    ->when(fn() => config('settings.is_self_hosted'))
    ->hourly()
    ->withoutOverlapping();
