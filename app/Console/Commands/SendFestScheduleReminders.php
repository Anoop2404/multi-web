<?php

namespace App\Console\Commands;

use App\Models\FestSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendFestScheduleReminders extends Command
{
    protected $signature = 'fest:schedule-reminders {--minutes=30 : Remind this many minutes before slot}';

    protected $description = 'Notify students/schools about upcoming fest performance slots';

    public function handle(NotificationService $notifier): int
    {
        $minutes = max(5, (int) $this->option('minutes'));
        $sent = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $minutes, $sahodaya, &$sent) {
                $windowStart = now();
                $windowEnd = now()->addMinutes($minutes);

                $slots = FestSchedule::whereNotNull('scheduled_at')
                    ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                    ->whereHas('event', fn ($q) => $q->whereIn('status', ['ongoing', 'registration_open']))
                    ->with(['event', 'item', 'participant.student', 'participant.registration'])
                    ->get();

                foreach ($slots as $slot) {
                    $participant = $slot->participant;
                    $student = $participant?->student;
                    if (! $student?->user_id) {
                        continue;
                    }

                    $user = User::find($student->user_id);
                    if (! $user) {
                        continue;
                    }

                    if (! ReminderDedupGuard::claim('fest:schedule-reminders', $sahodaya->id, $slot->id, $user->id)) {
                        continue;
                    }

                    $schoolId = $participant->registration?->school_id;
                    $notifier->notify(
                        $user,
                        'Fest performance reminder',
                        sprintf(
                            '%s — %s starts in about %d minutes%s.',
                            $slot->event?->title ?? 'Fest',
                            $slot->item?->title ?? 'Item',
                            $minutes,
                            $slot->stage ? " ({$slot->stage})" : ''
                        ),
                        $schoolId ? "/portal/student/{$schoolId}" : null,
                    );

                    $sent++;
                }
            });
        }

        $this->info("Sent {$sent} fest slot reminder(s).");

        return self::SUCCESS;
    }
}
