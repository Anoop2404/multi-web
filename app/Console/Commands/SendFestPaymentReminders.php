<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestEventNotifier;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendFestPaymentReminders extends Command
{
    protected $signature = 'fest:payment-reminders';

    protected $description = 'Notify schools with pending fest event fees';

    public function handle(FestEventNotifier $notifier): int
    {
        $sent = 0;

        foreach (Tenant::query()->sahodayas()->where('is_active', true)->get() as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $sahodaya, &$sent) {
                $events = FestEvent::query()
                    ->whereIn('status', ['published', 'registration_open', 'ongoing'])
                    ->get();

                foreach ($events as $event) {
                    if (! ReminderDedupGuard::claim('fest:payment-reminders', $sahodaya->id, $event->id, now()->format('Y-m-d'))) {
                        continue;
                    }

                    $sent += $notifier->paymentPendingReminders($event);
                }
            });
        }

        $this->info("Sent {$sent} fest payment reminder(s).");

        return self::SUCCESS;
    }
}
