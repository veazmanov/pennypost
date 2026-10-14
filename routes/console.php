<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;


// Hourly rather than only on delivery days: the command just delivers
// whatever is due, so a missed run (server down at 12:00) catches up an
// hour later instead of waiting for the next delivery day.
Schedule::command('messages:deliver')
    ->hourly()
    ->timezone('UTC');

Schedule::command('backup:database')
        ->weeklyOn(0, '03:00') // Sunday, off-peak
        ->timezone('UTC');
