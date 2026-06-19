<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue 3-day / 24-hour event reminders. Runs hourly; the command is
// window-based and idempotent, so a missed run simply catches up next hour.
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
