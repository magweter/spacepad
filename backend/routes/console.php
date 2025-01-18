<?php

use App\Console\Commands\RenewEventSubscriptions;
use Illuminate\Support\Facades\Schedule;

Schedule::command(RenewEventSubscriptions::class)->everyMinute();
