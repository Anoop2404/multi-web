<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestEventNotifier;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendFestRegistrationReminders extends Command
{
    protected $signature = 'fest:registration-reminders';

    protected $description = 'Notify schools when fest registration deadlines are approaching';

    public function handle(FestEventNotifier $notifier): int
    {
        $sent = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $sahodaya, &$sent) {
                $events = FestEvent::query()
                    ->whereNotNull('registration_close')
                    ->whereIn('status', ['published', 'registration_open'])
                    ->get();

                foreach ($events as $event) {
                    $daysLeft = (int) now()->startOfDay()->diffInDays($event->registration_close->startOfDay(), false);

                    if (! in_array($daysLeft, [1, 3], true)) {
                        continue;
                    }

                    if (! ReminderDedupGuard::claim('fest:registration-reminders', $sahodaya->id, $event->id, $daysLeft)) {
                        continue;
                    }

                    $notifier->registrationDeadlineReminder($event, $daysLeft);
                    $sent++;
                }
            });
        }

        $this->info("Sent {$sent} registration deadline reminder(s).");

        return self::SUCCESS;
    }
}
