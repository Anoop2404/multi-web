<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestEventNotifier;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendFestCompetitionReminders extends Command
{
    protected $signature = 'fest:competition-reminders';

    protected $description = 'Notify schools/participants 1 day before fest competition start';

    public function handle(FestEventNotifier $notifier): int
    {
        $sent = 0;

        foreach (Tenant::query()->sahodayas()->where('is_active', true)->get() as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $sahodaya, &$sent) {
                $events = FestEvent::query()
                    ->whereNotNull('event_start')
                    ->whereIn('status', ['published', 'registration_open', 'ongoing'])
                    ->get();

                foreach ($events as $event) {
                    $daysLeft = (int) now()->startOfDay()->diffInDays($event->event_start->startOfDay(), false);
                    if ($daysLeft !== 1) {
                        continue;
                    }

                    if (! ReminderDedupGuard::claim('fest:competition-reminders', $sahodaya->id, $event->id, 1)) {
                        continue;
                    }

                    $notifier->competitionReminder($event);
                    $sent++;
                }
            });
        }

        $this->info("Sent {$sent} competition reminder(s).");

        return self::SUCCESS;
    }
}
