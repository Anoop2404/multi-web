<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendTrainingReminders extends Command
{
    protected $signature = 'training:reminders {--payment : Also send fee-pending payment reminders}';

    protected $description = 'Notify teachers when training starts soon or registration is closing; optionally payment reminders';

    public function handle(NotificationService $notifier): int
    {
        $sent = 0;
        $includePayment = (bool) $this->option('payment');

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $includePayment, &$sent) {
                $sent += $this->sendProgramStartReminders($notifier);
                $sent += $this->sendRegistrationClosingReminders($notifier);

                if ($includePayment) {
                    $sent += $this->sendPaymentReminders($notifier);
                }
            });
        }

        $this->info("Sent {$sent} training reminder(s).");

        return self::SUCCESS;
    }

    private function sendProgramStartReminders(NotificationService $notifier): int
    {
        $sent = 0;

        $programs = TrainingProgram::query()
            ->whereNotNull('start_date')
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->get();

        foreach ($programs as $program) {
            $daysUntil = (int) now()->startOfDay()->diffInDays($program->start_date->startOfDay(), false);
            if ($daysUntil !== 1) {
                continue;
            }

            $registrations = TrainingRegistration::query()
                ->where('program_id', $program->id)
                ->whereIn('status', ['registered', 'confirmed'])
                ->with('teacher')
                ->get();

            foreach ($registrations as $registration) {
                if ($this->notifyTeacher(
                    $notifier,
                    $registration,
                    'training.reminder',
                    [
                        'program_title' => $program->title,
                        'start_date' => $program->start_date->format('j F Y'),
                        'venue' => $program->venue ?? '',
                    ],
                )) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function sendRegistrationClosingReminders(NotificationService $notifier): int
    {
        $sent = 0;

        $programs = TrainingProgram::query()
            ->whereNotNull('registration_close')
            ->whereIn('status', ['published', 'registration_open'])
            ->get();

        foreach ($programs as $program) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($program->registration_close->startOfDay(), false);
            if (! in_array($daysLeft, [1, 3], true)) {
                continue;
            }

            $registrations = TrainingRegistration::query()
                ->where('program_id', $program->id)
                ->whereIn('status', ['registered', 'confirmed'])
                ->with('teacher')
                ->get();

            foreach ($registrations as $registration) {
                if ($this->notifyTeacher(
                    $notifier,
                    $registration,
                    'training.registration.closing',
                    [
                        'program_title' => $program->title,
                        'start_date' => $program->start_date?->format('j F Y') ?? '',
                        'venue' => $program->venue ?? '',
                        'close_date' => $program->registration_close->format('j F Y'),
                        'days_left' => (string) $daysLeft,
                    ],
                )) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function sendPaymentReminders(NotificationService $notifier): int
    {
        $sent = 0;

        $registrations = TrainingRegistration::query()
            ->whereIn('status', ['registered', 'confirmed'])
            ->whereIn('fee_status', ['pending', 'partial', 'proof_uploaded'])
            ->whereHas('program', fn ($q) => $q->where('fee_type', 'flat')->where('fee_amount', '>', 0))
            ->with(['teacher', 'program', 'school'])
            ->get();

        foreach ($registrations as $registration) {
            $program = $registration->program;
            if (! $program) {
                continue;
            }

            if ($this->notifyTeacher(
                $notifier,
                $registration,
                'training.payment.reminder',
                [
                    'program_title' => $program->title,
                    'amount' => number_format((float) $registration->outstandingBalance(), 2),
                ],
            )) {
                $sent++;
            }
        }

        return $sent;
    }

    /** @param  array<string, string>  $replacements */
    private function notifyTeacher(
        NotificationService $notifier,
        TrainingRegistration $registration,
        string $slug,
        array $replacements,
    ): bool {
        $userId = $registration->teacher?->user_id;
        if (! $userId) {
            return false;
        }

        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        $schoolId = $registration->school_id;
        $actionUrl = $schoolId ? "/portal/teacher/{$schoolId}/training" : null;

        $notifier->notifyFromTemplate($user, $slug, $replacements, $actionUrl);

        return true;
    }
}
