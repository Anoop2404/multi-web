<?php

namespace App\Console\Commands;

use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqExamSessionService;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class AutoSubmitExpiredMcqExams extends Command
{
    protected $signature = 'mcq:auto-submit-expired';

    protected $description = 'Auto-submit online MCQ exams that exceeded their time limit';

    public function handle(McqExamSessionService $sessions, PlatformAuditLogger $audit): int
    {
        $submitted = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($sessions, $audit, &$submitted) {
                McqRegistration::query()
                    ->where('status', 'started')
                    ->whereNotNull('started_at')
                    ->with(['exam', 'mark'])
                    ->chunkById(50, function ($registrations) use ($sessions, $audit, &$submitted) {
                        foreach ($registrations as $registration) {
                            if (! $sessions->isExpired($registration)) {
                                continue;
                            }

                            // Engine exams are finalised by the exam engine's sweeper and graded
                            // by exam-engine:consume — never from the in-app autosave here.
                            if ($registration->exam && \App\Services\ExamEngine\ExamEngineRun::usesEngine($registration->exam)) {
                                continue;
                            }

                            try {
                                // Grade what the student had autosaved (previously an empty answer set).
                                $sessions->submit($registration, $registration->draft_answers ?? []);
                                $audit->mcqRegistration(
                                    $registration->fresh(['exam']),
                                    'mcq.exam.auto_submitted',
                                    "Auto-submitted expired exam for registration #{$registration->id}",
                                );
                                $submitted++;
                            } catch (ValidationException) {
                                // Already submitted or invalid state — skip.
                            }
                        }
                    });
            });
        }

        $this->info("Auto-submitted {$submitted} expired MCQ exam(s).");

        return self::SUCCESS;
    }
}
