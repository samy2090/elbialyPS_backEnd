<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto-ending of expired activities every 5 minutes
Schedule::command('activities:auto-end')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
