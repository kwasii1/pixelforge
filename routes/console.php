<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The safety net for missed webhooks. On Laravel Cloud every scheduled run wakes a
 * sleeping environment, so production runs it less often to let the app scale to zero.
 */
Schedule::command('pixelforge:sync-generations')
    ->cron('*/'.max(1, (int) config('services.pixelforge.sync_interval_minutes')).' * * * *')
    ->withoutOverlapping();
