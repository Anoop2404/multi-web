<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('board-results:upload-reminders')->weeklyOn(1, '09:30');
Schedule::command('fest:registration-reminders')->dailyAt('09:00');
Schedule::command('fest:schedule-reminders')->everyFifteenMinutes();
Schedule::command('training:reminders --payment')->dailyAt('09:15');
Schedule::command('training:session-reminders')->hourly();
Schedule::command('mcq:auto-submit-expired')->everyFiveMinutes();
Schedule::command('mcq:transition-exam-windows')->everyFifteenMinutes();
Schedule::command('mcq:exam-reminders')->hourly();
Schedule::command('membership:update-renewal-status')->dailyAt('02:00');
Schedule::command('membership:send-reminders')->dailyAt('08:30');
Schedule::command('erp:retry-failed-receipt-emails')->hourly();
Schedule::command('erp:school-document-expiry-reminders')->dailyAt('08:00');
Schedule::command('erp:mark-school-documents-expired')->dailyAt('02:30');
