<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule recurring transactions to be processed daily at 1:00 AM
Schedule::command('recurring:process')->dailyAt('01:00');

// Clean up expired pending uploads hourly
Schedule::command('uploads:cleanup-pending')->hourly();
