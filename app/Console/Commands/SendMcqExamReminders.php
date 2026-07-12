<?php

namespace App\Console\Commands;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Mcq\McqExamNotifier;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendMcqExamReminders extends Command
{
    protected $signature = 'mcq:exam-reminders {--hours=24 : Hours before scheduled_at to send reminders}';

    protected $description = 'Notify registered students/teachers and school admins before an MCQ exam starts';

    public function handle(McqExamNotifier $notifier): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $sent = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $hours, &$sent, $sahodaya) {
                $windowStart = now()->addHours($hours - 1);
                $windowEnd = now()->addHours($hours);

                $exams = McqExam::query()
                    ->whereNotNull('scheduled_at')
                    ->whereIn('status', ['published', 'ongoing'])
                    ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                    ->get();

                foreach ($exams as $exam) {
                    $registrations = McqRegistration::query()
                        ->where('exam_id', $exam->id)
                        ->where('status', '!=', 'cancelled')
                        ->whereIn('approval_status', ['approved', 'pending_approval', 'pending_payment'])
                        ->with(['student.user', 'teacher', 'exam'])
                        ->get();

                    foreach ($registrations as $registration) {
                        if (! ReminderDedupGuard::claim('mcq:exam-reminders', $sahodaya->id, $registration->id)) {
                            continue;
                        }

                        if ($notifier->examReminder($registration)) {
                            $sent++;
                        }
                    }
                }
            });
        }

        $this->info("Sent {$sent} MCQ exam reminder(s).");

        return self::SUCCESS;
    }
}
