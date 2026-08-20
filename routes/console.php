<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('commissions:generate-monthly')->monthlyOn(7, '00:05');
Schedule::command('follow-ups:send-reminders')->dailyAt('07:00');
Schedule::command('stations:morning-count')->dailyAt('09:05');

// Platform disaster-recovery ZIP — copy storage/app/backup-temp/ off the server.
Schedule::command('data:backup-export --platform')->weeklyOn(0, '02:30');
