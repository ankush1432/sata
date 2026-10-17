<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Phase 16 Scheduled Automation Jobs
|--------------------------------------------------------------------------
|
| - subscriptions:process-expiry: Reconciles active, expiring, and expired subscriptions
| - notifications:process-reminders: Dispatches 7d, 3d, 1d expiry notifications
| - devices:mark-offline: Marks inactive devices offline after threshold
| - security-commands:expire: Sweeps expired pending security commands
|
*/
Schedule::command('subscriptions:process-expiry')->hourly()->withoutOverlapping();
Schedule::command('notifications:process-reminders')->hourly()->withoutOverlapping();
Schedule::command('devices:mark-offline')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('security-commands:expire')->everyFifteenMinutes()->withoutOverlapping();

