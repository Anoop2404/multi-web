<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('board-results:upload-reminders')->weeklyOn(1, '09:30')->withoutOverlapping();
Schedule::command('board-results:principal-verification-reminders')->weeklyOn(1, '09:45')->withoutOverlapping();
Schedule::command('fest:registration-reminders')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('fest:competition-reminders')->dailyAt('09:05')->withoutOverlapping();
Schedule::command('fest:payment-reminders')->dailyAt('10:00')->withoutOverlapping();
Schedule::command('fest:schedule-reminders')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('training:reminders --payment')->dailyAt('09:15')->withoutOverlapping();
Schedule::command('training:session-reminders')->hourly()->withoutOverlapping();
Schedule::command('mcq:auto-submit-expired')->everyFiveMinutes()->withoutOverlapping(5);
Schedule::command('mcq:transition-exam-windows')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('mcq:exam-reminders')->hourly()->withoutOverlapping();
Schedule::command('membership:update-renewal-status')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('subscriptions:process-lifecycle')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('membership:send-reminders')->dailyAt('08:30')->withoutOverlapping();
Schedule::command('erp:retry-failed-receipt-emails')->hourly()->withoutOverlapping();
Schedule::command('erp:school-document-expiry-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('erp:mark-school-documents-expired')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('platform:snapshot-dashboard')->dailyAt('02:45')->withoutOverlapping();

// State Kalolsavam qualifier submissions (docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §5) — retries
// pending/failed Sahodaya-to-State outbox rows. Was never scheduled; nothing sent these otherwise.
Schedule::command('fest:process-state-outbox')->everyFiveMinutes()->withoutOverlapping();
