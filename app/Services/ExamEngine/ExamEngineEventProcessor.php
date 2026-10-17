<?php

namespace App\Services\ExamEngine;

use App\Models\McqExam;
use App\Models\McqProctorEvent;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqExamSessionService;
use App\Services\Mcq\McqGrader;
use App\Services\Mcq\McqPaperService;
use App\Support\TenancyDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies exam engine events to the Sahodaya's database (exam-engine/CONTRACT.md §6): check-ins,
 * starts, answer snapshots, submissions (graded here with McqGrader — the engine never sees the
 * answer key), proctor signals and audit entries. Delivery is at-least-once, so every handler
 * is idempotent.
 */
class ExamEngineEventProcessor
{
    /** @var array<string, array<int|string, array<string, mixed>>> run → reg_id → score, for show-score write-back */
    private array $results = [];

    public function __construct(
        private readonly McqGrader $grader,
        private readonly McqPaperService $papers,
        private readonly McqExamSessionService $sessions,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<string> ids of events handled (or safely skipped), to acknowledge
     */
    public function process(array $events): array
    {
        $handled = [];
        $this->results = [];

        foreach (collect($events)->groupBy(fn ($e) => (string) ($e['run'] ?? '')) as $run => $runEvents) {
            [$tenantId, $examId] = array_pad(explode(':', (string) $run, 2), 2, null);
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            if (! $tenant || ! is_numeric($examId)) {
                Log::warning('Exam engine event for an unknown run; acknowledging and skipping.', ['run' => $run]);
                $handled = array_merge($handled, $runEvents->pluck('id')->all());

                continue;
            }

            TenancyDatabase::withTenantDatabase($tenant, function () use ($runEvents, $examId, &$handled) {
                $exam = McqExam::find((int) $examId);
                foreach ($runEvents as $event) {
                    try {
                        if ($exam) {
                            DB::transaction(fn () => $this->handle($exam, $event));
                        }
                        $handled[] = $event['id'];
                    } catch (\Throwable $e) {
                        // Leave it unacknowledged: the engine redelivers it on the next claim.
                        Log::error('Exam engine event failed', ['id' => $event['id'] ?? null, 'type' => $event['type'] ?? null, 'error' => $e->getMessage()]);
                        report($e);
                    }
                }
            });
        }

        return $handled;
    }

    /** @return array<string, array<int|string, array<string, mixed>>> scores to write back per run (show-score exams) */
    public function pendingResults(): array
    {
        return $this->results;
    }

    private function handle(McqExam $exam, array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $data = (array) ($event['data'] ?? []);
        $at = isset($event['at']) ? Carbon::parse($event['at']) : now();

        if ($type === 'run.extended') {
            // Kept at exam level: folding it into each registration's extra_time_minutes would be
            // re-sent as roster extra_minutes and counted twice by the engine (NOTES-engine.md).
            $settings = $exam->settings_json ?? [];
            $settings['engine_extended_minutes'] = (int) ($settings['engine_extended_minutes'] ?? 0) + max(0, (int) ($data['minutes'] ?? 0));
            $exam->update(['settings_json' => $settings]);
            if (! str_starts_with((string) ($data['actor'] ?? ''), 'user:')) {
                $this->audit($exam, null, 'mcq.engine.extend_all', 'Added '.(int) ($data['minutes'] ?? 0).' min for every candidate', $data);
            }

            return;
        }

        if ($type === 'run.hold') {
            $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], ['engine_hold' => (bool) ($data['on'] ?? false)])]);

            return;
        }

        if (in_array($type, ['run.broadcast', 'run.closed'], true)) {
            $this->audit($exam, null, 'mcq.engine.'.str_replace('run.', '', $type), $type === 'run.broadcast'
                ? 'Broadcast to candidates: '.mb_strimwidth((string) ($data['message'] ?? ''), 0, 200, '…')
                : 'Closed the online exam on the engine', $data);
            if ($type === 'run.closed') {
                $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], ['engine_state' => 'closed'])]);
            }

            return;
        }

        $registration = McqRegistration::where('exam_id', $exam->id)->find((int) ($event['reg_id'] ?? 0));
        if (! $registration) {
            return;
        }

        match ($type) {
            'attempt.checked_in'      => $this->checkedIn($registration, $data, $at),
            'attempt.started'         => $this->started($registration, $at),
            'attempt.snapshot'        => $this->snapshot($registration, $data),
            'attempt.submitted'       => $this->submitted($exam, $registration, $data, $at),
            'attempt.device_switched' => $this->deviceSwitched($exam, $registration, $data, $at),
            'attempt.action'          => $this->action($exam, $registration, $data),
            'proctor.batch'           => $this->proctor($registration, $data),
            default                   => null,
        };
    }

    private function checkedIn(McqRegistration $registration, array $data, Carbon $at): void
    {
        $registration->update(array_filter([
            'checked_in_at' => $registration->checked_in_at ?? $at,
            'last_ip'       => $data['ip'] ?? null,
            'device_info'   => isset($data['user_agent']) ? ['user_agent' => mb_substr((string) $data['user_agent'], 0, 300), 'method' => $data['method'] ?? null] : null,
        ], fn ($v) => $v !== null));
    }

    private function started(McqRegistration $registration, Carbon $at): void
    {
        if ($registration->status === 'submitted') {
            return;
        }

        $registration->update(array_filter([
            'status'            => 'started',
            'started_at'        => $registration->started_at ?? $at,
            // Writing online is attendance for an engine exam.
            'attendance_status' => in_array($registration->attendance_status, [null, 'pending'], true) ? 'present' : null,
            'attendance_marked_at' => in_array($registration->attendance_status, [null, 'pending'], true) ? $at : null,
        ], fn ($v) => $v !== null));
    }

    private function snapshot(McqRegistration $registration, array $data): void
    {
        if ($registration->status !== 'submitted' && is_array($data['answers'] ?? null)) {
            $registration->update(['draft_answers' => $data['answers']]);
        }
    }

    private function submitted(McqExam $exam, McqRegistration $registration, array $data, Carbon $at): void
    {
        if ($registration->status === 'submitted' && $registration->mark) {
            return; // already graded (redelivery)
        }

        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $result = $this->grader->grade($this->papers->questionsForExam($exam), $answers, $exam->multiMarkingMode());
        $mark = $this->sessions->storeGradedResult($registration, $result);

        $registration->update([
            'status'         => 'submitted',
            'started_at'     => $registration->started_at ?? $at,
            'submitted_at'   => $at,
            'submit_mode'    => in_array($data['mode'] ?? null, ['manual', 'auto', 'forced'], true) ? $data['mode'] : 'manual',
            'engine_receipt' => isset($data['receipt']) ? mb_substr((string) $data['receipt'], 0, 40) : null,
            'draft_answers'  => $answers,
        ]);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.exam.engine_submitted',
            sprintf('Submitted on the exam engine (%s, %d late change(s)); score %s', $data['mode'] ?? 'manual', (int) ($data['late_changes'] ?? 0), $mark->score),
        );

        if (ExamEngineRun::settings($exam)['show_score_on_submit']) {
            $this->results[ExamEngineRun::for($exam)][$registration->id] = [
                'score'      => (float) $mark->score,
                'max_score'  => (float) $result['max_score'],
                'percentage' => (float) $mark->percentage,
                'grade'      => $mark->grade,
            ];
        }
    }

    private function deviceSwitched(McqExam $exam, McqRegistration $registration, array $data, Carbon $at): void
    {
        $registration->update(['last_ip' => $data['ip'] ?? $registration->last_ip]);
        $this->proctor($registration, ['events' => [['type' => 'device_switch', 'at' => $at->toIso8601String()]]]);
        $this->audit($exam, $registration, 'mcq.engine.device_switched', 'Continued the exam on another device', $data);
    }

    private function action(McqExam $exam, McqRegistration $registration, array $data): void
    {
        // Actions taken from Laravel's live monitor (actor "user:…") were already recorded and
        // audited there, including the extra minutes.
        if (str_starts_with((string) ($data['actor'] ?? ''), 'user:')) {
            return;
        }

        if (($data['action'] ?? null) === 'extend' && isset($data['minutes'])) {
            $registration->increment('extra_time_minutes', (int) $data['minutes']);
        }

        $this->audit($exam, $registration, 'mcq.engine.'.preg_replace('/[^a-z_]/', '', (string) ($data['action'] ?? 'action')), sprintf(
            '%s by %s%s',
            ucfirst(str_replace('_', ' ', (string) ($data['action'] ?? 'action'))),
            $data['actor'] ?? 'staff',
            filled($data['reason'] ?? null) ? ' — '.mb_strimwidth((string) $data['reason'], 0, 200, '…') : '',
        ), $data);
    }

    private function proctor(McqRegistration $registration, array $data): void
    {
        $allowed = array_merge(McqProctorEvent::TYPES, McqProctorEvent::ENGINE_TYPES);
        $room = McqProctorEvent::MAX_PER_REGISTRATION - McqProctorEvent::where('registration_id', $registration->id)->count();

        foreach (array_slice((array) ($data['events'] ?? []), 0, max(0, $room)) as $item) {
            if (! in_array($item['type'] ?? null, $allowed, true)) {
                continue;
            }
            McqProctorEvent::create([
                'registration_id' => $registration->id,
                'event_type'      => $item['type'],
                'occurred_at'     => isset($item['at']) ? Carbon::parse($item['at']) : now(),
            ]);
        }
    }

    private function audit(McqExam $exam, ?McqRegistration $registration, string $action, string $description, array $data): void
    {
        $logger = app(PlatformAuditLogger::class);
        $properties = array_intersect_key($data, array_flip(['action', 'minutes', 'reason', 'actor', 'ip', 'unfinished_forced']));

        $registration
            ? $logger->mcq($exam, $action, $description, $properties + ['registration_id' => $registration->id, 'school_id' => $registration->school_id], $registration)
            : $logger->mcq($exam, $action, $description, $properties);
    }
}
