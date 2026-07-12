<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendTrainingSessionReminders extends Command
{
    protected $signature = 'training:session-reminders {--hours=6 : Remind this many hours before session}';

    protected $description = 'Notify confirmed teachers about upcoming training sessions (within N hours)';

    public function handle(NotificationService $notifier): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $sent = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $hours, &$sent, $sahodaya) {
                $windowStart = now();
                $windowEnd = now()->addHours($hours);

                $sessions = TrainingSession::query()
                    ->whereNotNull('scheduled_at')
                    ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                    ->whereHas('program', fn ($q) => $q->whereIn('status', ['published', 'registration_open', 'ongoing']))
                    ->with('program')
                    ->get();

                foreach ($sessions as $session) {
                    $program = $session->program;
                    if (! $program) {
                        continue;
                    }

                    $registrations = TrainingRegistration::query()
                        ->where('program_id', $program->id)
                        ->whereIn('status', ['confirmed', 'registered'])
                        ->with('teacher')
                        ->get();

                    foreach ($registrations as $registration) {
                        $userId = $registration->teacher?->user_id;
                        if (! $userId) {
                            continue;
                        }

                        $user = User::find($userId);
                        if (! $user) {
                            continue;
                        }

                        if (! \App\Support\ReminderDedupGuard::claim(
                            'training:session-reminders',
                            $sahodaya->id,
                            $session->id,
                            $user->id,
                        )) {
                            continue;
                        }

                        $schoolId = $registration->school_id;
                        $notifier->notifyFromTemplate(
                            $user,
                            'training.session.reminder',
                            [
                                'program_title' => $program->title,
                                'session_title' => $session->title ?? 'Session',
                                'scheduled_at' => $session->scheduled_at->format('j F Y g:i A'),
                                'venue' => $session->venue ?: ($program->venue ?? ''),
                            ],
                            $schoolId ? "/portal/teacher/{$schoolId}/training" : null,
                        );
                        $sent++;
                    }
                }
            });
        }

        $this->info("Sent {$sent} training session reminder(s).");

        return self::SUCCESS;
    }
}
