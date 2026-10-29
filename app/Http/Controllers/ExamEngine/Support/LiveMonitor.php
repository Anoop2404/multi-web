<?php

namespace App\Http\Controllers\ExamEngine\Support;

use App\Models\AuditLog;
use App\Models\ExamEngineBooking;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\ExamEngine\ExamEngineBridge;
use App\Services\ExamEngine\ExamEngineClient;
use App\Services\ExamEngine\ExamEngineException;
use App\Services\ExamEngine\ExamEngineRun;
use App\Services\Mcq\McqExamPasscodeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Live monitor + actions over the exam engine's internal API (exam-engine/CONTRACT.md §3),
 * shared by the Sahodaya panel, the exam portal and the school coordinator view. Every read
 * and action is narrowed by a LiveMonitorScope; every action is audited with its actor.
 * Engine failures never become a 500: reads come back as { ok: false, error }, actions as 4xx/503 JSON.
 */
class LiveMonitor
{
    public const ATTEMPT_ACTIONS = ['extend', 'force_submit', 'lock', 'unlock', 'allow_switch', 'reissue_passcode'];

    /** First-line support for school coordinators (plan §5.4). */
    public const COORDINATOR_ACTIONS = ['extend', 'unlock', 'allow_switch', 'reissue_passcode'];

    public const STATES = ['checked_in', 'waiting', 'writing', 'submitted', 'locked'];

    public const PACE_OPTIONS = [20, 30, 60];

    public const PER_PAGE = 50;

    /** Upper bound for one extension by the Sahodaya team. */
    public const MAX_EXTEND_MINUTES = 240;

    /** Summaries are shared by every viewer of a run; a short cache keeps 5 s polling cheap for the engine. */
    private const SUMMARY_CACHE_SECONDS = 2;

    public function __construct(
        private readonly ExamEngineClient $client,
        private readonly McqExamPasscodeService $passcodes,
        private readonly ExamEngineBridge $bridge,
    ) {}

    /** "user:12 (exam_controller)" — the actor string the engine records with each action. */
    public static function actorFor(?object $user, array $preferredRoles = []): string
    {
        if (! $user) {
            return 'user:0 (unknown)';
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        $role = collect($preferredRoles)->first(fn (string $r) => in_array($r, $roles, true))
            ?? ($roles[0] ?? (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? 'superadmin' : 'user'));

        return 'user:'.$user->getAuthIdentifier()." ({$role})";
    }

    // ── status and reads ──────────────────────────────────────────────────

    /** Whether the exam can be monitored on the engine, and why not when it can't. */
    public function engineStatus(McqExam $exam): array
    {
        $settings = ExamEngineRun::settings($exam);
        $state = ExamEngineRun::state($exam);
        $usesEngine = ExamEngineRun::usesEngine($exam);
        $configured = $this->client->isConfigured();
        $checkinOpensAt = $exam->scheduled_at
            ? $exam->scheduled_at->copy()->subHours((int) ($settings['checkin_hours'] ?? 48))
            : null;

        [$reason, $message] = match (true) {
            ! $exam->isOnlineDelivery() => ['not_online', 'This exam is written on paper, so there is no live online sitting to monitor.'],
            ! $usesEngine => ['not_engine', 'This online exam runs inside the portal, not on the online exam engine. Use the "Live session" page to follow it.'],
            ! $configured => ['not_configured', 'The online exam engine is not connected on this server yet (EXAM_ENGINE_INTERNAL_URL / EXAM_ENGINE_INTERNAL_TOKEN). Ask the platform team.'],
            $state === null => ['not_armed', 'This exam is not armed on the exam engine yet, so nobody can log in. It is armed automatically when check-in opens'
                .($checkinOpensAt ? ' ('.$checkinOpensAt->timezone(config('app.timezone'))->format('j M Y, g:i A').')' : '')
                .', or by the Sahodaya from the exam\'s Paper tab.'],
            default => [null, null],
        };

        return [
            'uses_engine'      => $usesEngine,
            'state'            => $state,
            'configured'       => $configured,
            'available'        => $reason === null,
            'reason'           => $reason,
            'message'          => $message,
            'closed'           => $state === 'closed',
            'checkin_opens_at' => $checkinOpensAt?->toIso8601String(),
            'starts_at'        => $exam->scheduled_at?->toIso8601String(),
            'supervision'      => $settings['supervision'] ?? 'open',
            'coordinator_extend_cap_minutes' => (int) ($settings['coordinator_extend_cap_minutes'] ?? 0),
        ];
    }

    /** Filters from the query string; a school scope always pins school_id to its own school. */
    public function filters(Request $request, LiveMonitorScope $scope): array
    {
        $state = $request->query('state');
        $schoolId = $request->query('school_id');

        return [
            'school_id' => $scope->schoolId ?? (is_string($schoolId) && $schoolId !== '' && strlen($schoolId) <= 64 ? $schoolId : null),
            'state'     => is_string($state) && in_array($state, self::STATES, true) ? $state : null,
            'q'         => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'flagged'   => $request->boolean('flagged'),
            'page'      => max(1, min(10000, (int) $request->query('page', 1))),
        ];
    }

    /** One poll: summary + one page of attempts, already narrowed to the scope. */
    public function snapshot(McqExam $exam, LiveMonitorScope $scope, array $filters): array
    {
        $status = $this->engineStatus($exam);
        $base = ['fetched_at' => now()->toIso8601String(), 'engine_state' => $status['state']];

        if (! $status['available']) {
            return $base + ['ok' => false, 'reason' => $status['reason'], 'error' => $status['message'], 'summary' => null, 'attempts' => null];
        }

        try {
            $summary = Cache::remember(
                'exam-engine:summary:'.ExamEngineRun::for($exam),
                self::SUMMARY_CACHE_SECONDS,
                fn () => $this->client->summary($exam),
            );
            $attempts = $this->client->attempts($exam, [
                'school_id' => $scope->schoolId ?? $filters['school_id'],
                'state'     => $filters['state'],
                'q'         => $filters['q'],
                'flagged'   => $filters['flagged'] ? 1 : null,
                'page'      => $filters['page'],
                'per_page'  => self::PER_PAGE,
            ]);
        } catch (ExamEngineException $e) {
            return $base + [
                'ok'       => false,
                'reason'   => 'engine_error',
                'error'    => 'The exam engine did not answer: '.$e->getMessage(),
                'summary'  => null,
                'attempts' => null,
            ];
        }

        return $base + [
            'ok'       => true,
            'reason'   => null,
            'error'    => null,
            'summary'  => $this->presentSummary($summary, $scope),
            'attempts' => $this->presentAttempts($attempts, $summary['server_time'] ?? null, $scope, $filters['page']),
        ];
    }

    /** Laravel's own count of candidates who should be on the roster, for reconciliation. */
    public function registeredCount(McqExam $exam, ?string $schoolId = null): int
    {
        return McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->where('approval_status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->count();
    }

    /** Page props shared by the three monitor pages. */
    public function pageProps(McqExam $exam, LiveMonitorScope $scope, array $filters, array $endpoints): array
    {
        $status = $this->engineStatus($exam);

        return [
            'exam' => [
                'id'                => $exam->id,
                'title'             => $exam->title,
                'status'            => $exam->status,
                'delivery_mode'     => $exam->delivery_mode,
                'results_published' => (bool) $exam->results_published,
                'is_model_exam'     => $exam->isModelExam(),
                'scheduled_at'      => $exam->scheduled_at?->toIso8601String(),
                'duration_minutes'  => (int) $exam->duration_minutes,
            ],
            'engine'          => $status,
            'permissions'     => $scope->toArray(),
            'endpoints'       => $endpoints,
            'filters'         => $filters,
            'states'          => self::STATES,
            'paceOptions'     => self::PACE_OPTIONS,
            'registeredCount' => $this->registeredCount($exam, $scope->schoolId),
            'pollSeconds'     => $scope->isSchool() ? 10 : 5,
            'initial'         => $this->snapshot($exam, $scope, $filters),
        ];
    }

    // ── actions ───────────────────────────────────────────────────────────

    /**
     * The registration an action targets. Refused before any engine call when it isn't on
     * this exam, or (school scope) isn't from the viewer's school.
     */
    public function registrationFor(McqExam $exam, int $regId, LiveMonitorScope $scope): McqRegistration
    {
        $registration = McqRegistration::where('exam_id', $exam->id)->find($regId);
        abort_unless($registration, 404, 'That candidate is not registered for this exam.');
        abort_if($scope->schoolId !== null && (string) $registration->school_id !== $scope->schoolId, 403, 'That candidate is not from your school.');

        return $registration;
    }

    public function attemptAction(Request $request, McqExam $exam, McqRegistration $registration, LiveMonitorScope $scope): JsonResponse
    {
        $action = (string) $request->input('action');
        abort_unless(in_array($action, array_diff(self::ATTEMPT_ACTIONS, ['reissue_passcode']), true), 422, 'Unknown action.');
        abort_unless($scope->can($action), 403, 'You can view this exam but not take this action.');

        $maxMinutes = $scope->extendCap ?? self::MAX_EXTEND_MINUTES;
        $data = $request->validate(match ($action) {
            'extend' => [
                'minutes' => ['required', 'integer', 'min:1', 'max:'.max(1, $maxMinutes)],
                'reason'  => ['required', 'string', 'max:500'],
            ],
            'force_submit', 'lock' => ['reason' => ['required', 'string', 'max:500']],
            default => ['reason' => ['nullable', 'string', 'max:500']],
        });

        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        if ($action === 'extend' && $scope->extendCap !== null) {
            $used = $this->coordinatorExtendUsed([$registration->id])[$registration->id] ?? 0;
            $left = max(0, $scope->extendCap - $used);
            if ((int) $data['minutes'] > $left) {
                throw ValidationException::withMessages(['minutes' => $left === 0
                    ? "This candidate already has the {$scope->extendCap} extra minutes a school can give. Ask the Sahodaya exam team for more."
                    : "A school can add {$scope->extendCap} minutes in total per candidate; {$left} left for this candidate."]);
            }
        }

        $payload = array_filter([
            'minutes' => $action === 'extend' ? (int) $data['minutes'] : null,
            'reason'  => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
        ], fn ($v) => $v !== null);

        try {
            $row = $this->client->action($exam, $registration->id, $action, $payload, $scope->actor);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }

        // Record it here at once (not when the engine's event arrives), so a roster re-send in
        // between — which sets extra_minutes absolutely — can't undo it (NOTES-engine.md §25).
        if ($action === 'extend') {
            $registration->increment('extra_time_minutes', (int) $payload['minutes']);
        }

        $registration->loadMissing(['student:id,name', 'teacher:id,name']);
        $who = $registration->participantName().($registration->hall_ticket_no ? " (hall ticket {$registration->hall_ticket_no})" : '');
        $label = match ($action) {
            'extend'       => "Gave {$payload['minutes']} extra minutes to {$who}",
            'force_submit' => "Force-submitted the attempt of {$who}",
            'lock'         => "Locked the attempt of {$who}",
            'unlock'       => "Unlocked the attempt of {$who}",
            'allow_switch' => "Allowed {$who} to continue on another device",
        };

        $this->audit($exam, "mcq.engine.{$action}", $label.(isset($payload['reason']) ? ' — '.$payload['reason'] : ''), $scope, $payload + [
            'registration_id' => $registration->id,
            'school_id'       => $registration->school_id,
            'student_id'      => $registration->student_id,
        ], $registration);

        $attempt = is_array($row) && isset($row['reg_id']) ? $this->presentRow($row, null) : null;
        if ($attempt && $scope->schoolId !== null && $attempt['school_id'] !== $scope->schoolId) {
            $attempt = null;
        }

        return response()->json(['ok' => true, 'message' => $label.'.', 'attempt' => $attempt]);
    }

    /** New passcode (lost hall ticket, suspected sharing), pushed to the engine roster; shown once. */
    public function reissuePasscode(McqExam $exam, McqRegistration $registration, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('reissue_passcode'), 403, 'You can view this exam but not reissue passcodes.');
        abort_unless(ExamEngineRun::usesEngine($exam), 422, 'This exam does not use the online exam engine.');

        if (! $registration->hall_ticket_no) {
            return response()->json(['ok' => false, 'message' => 'Issue the hall ticket first — the passcode is created with it.'], 422);
        }

        $code = McqExamPasscodeService::format($this->passcodes->reissue($registration));
        $pushed = true;
        $warning = null;
        try {
            $this->bridge->pushRosterEntry($registration);
        } catch (\Throwable $e) {
            if (! $e instanceof ExamEngineException) {
                report($e);
            }
            $pushed = false;
            $warning = 'The new passcode is saved here but could not be sent to the exam engine yet ('.$e->getMessage().'). Try again in a minute; until then the candidate cannot log in with it.';
        }

        $registration->loadMissing(['student:id,name', 'teacher:id,name']);
        $this->audit($exam, 'mcq.engine.passcode_reissued', 'Reissued the online exam passcode of '.$registration->participantName().($pushed ? '' : ' (not yet on the engine)'), $scope, [
            'registration_id' => $registration->id,
            'school_id'       => $registration->school_id,
            'student_id'      => $registration->student_id,
            'pushed'          => $pushed,
        ], $registration);

        return response()->json([
            'ok'       => true,
            'passcode' => $code,
            'pushed'   => $pushed,
            'warning'  => $warning,
            'message'  => "New passcode for {$registration->participantName()}. The old one no longer works.",
        ]);
    }

    public function broadcast(Request $request, McqExam $exam, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('broadcast'), 403, 'Only the Sahodaya exam team can broadcast.');
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);
        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        $message = trim($data['message']);
        try {
            $this->client->broadcast($exam, $message, $scope->actor);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }

        $this->audit($exam, 'mcq.engine.broadcast', 'Broadcast to candidates: '.mb_strimwidth($message, 0, 200, '…'), $scope, ['message' => $message]);

        return response()->json(['ok' => true, 'message' => 'Message sent. Candidates see it on their next sync.']);
    }

    public function pace(Request $request, McqExam $exam, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('pace'), 403, 'Only the Sahodaya exam team can change pacing.');
        $data = $request->validate(['seconds' => ['present', 'nullable', 'integer', 'in:'.implode(',', self::PACE_OPTIONS)]]);
        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        $seconds = $data['seconds'] === null ? null : (int) $data['seconds'];
        try {
            $this->client->pace($exam, $seconds);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }
        Cache::forget('exam-engine:summary:'.ExamEngineRun::for($exam));

        $label = $seconds === null ? 'automatic' : "every {$seconds} s";
        $this->audit($exam, 'mcq.engine.pace', "Set answer sync pacing to {$label}", $scope, ['seconds' => $seconds]);

        return response()->json(['ok' => true, 'message' => "Sync pacing set to {$label}."]);
    }

    /** Incident tool: adds minutes for every candidate (e.g. after a regional power or network cut). */
    public function extendAll(Request $request, McqExam $exam, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('extend_all'), 403, 'Only the Sahodaya exam team can extend the whole exam.');
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'reason'  => ['required', 'string', 'max:300'],
        ]);
        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        try {
            $this->client->extendRun($exam, (int) $data['minutes'], $scope->actor);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }
        Cache::forget('exam-engine:summary:'.ExamEngineRun::for($exam));

        $this->audit($exam, 'mcq.engine.extend_all', "Added {$data['minutes']} min for every candidate — ".mb_strimwidth($data['reason'], 0, 200, '…'), $scope, [
            'minutes' => (int) $data['minutes'], 'reason' => $data['reason'],
        ]);

        return response()->json(['ok' => true, 'message' => "Added {$data['minutes']} minutes for every candidate."]);
    }

    /** Incident tool: stop (or resume) automatic submission at the deadline while a problem is sorted out. */
    public function hold(Request $request, McqExam $exam, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('hold'), 403, 'Only the Sahodaya exam team can hold auto-submission.');
        $data = $request->validate([
            'on'     => ['required', 'boolean'],
            'reason' => ['required_if:on,true', 'nullable', 'string', 'max:300'],
        ]);
        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        $on = (bool) $data['on'];
        try {
            $this->client->holdRun($exam, $on, $scope->actor);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }
        Cache::forget('exam-engine:summary:'.ExamEngineRun::for($exam));

        $this->audit($exam, $on ? 'mcq.engine.hold_on' : 'mcq.engine.hold_off', $on
            ? 'Held automatic submission — '.mb_strimwidth((string) $data['reason'], 0, 200, '…')
            : 'Resumed automatic submission', $scope, ['reason' => $data['reason'] ?? null]);

        return response()->json(['ok' => true, 'message' => $on
            ? 'Automatic submission is on hold. Candidates past their deadline are paused, not submitted.'
            : 'Automatic submission resumed.']);
    }

    /** Ends the sitting: force-submits anything still open and stops logins (CONTRACT §2.4). */
    public function close(Request $request, McqExam $exam, LiveMonitorScope $scope): JsonResponse
    {
        abort_unless($scope->can('close'), 403, 'Only the Sahodaya exam team can close the exam.');
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that you have checked the reconciliation before closing.']);
        if ($blocked = $this->blockedUnlessArmed($exam)) {
            return $blocked;
        }

        try {
            $result = $this->client->close($exam);
        } catch (ExamEngineException $e) {
            return $this->engineError($e);
        }

        $unfinished = (int) ($result['unfinished'] ?? 0);
        $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], [
            'engine_state'     => 'closed',
            'engine_closed_at' => now()->toIso8601String(),
        ])]);
        ExamEngineBooking::where('tenant_id', $exam->tenant_id)->where('exam_id', $exam->id)
            ->whereIn('status', ExamEngineBooking::ACTIVE_STATUSES)
            ->update(['status' => 'closed']);
        Cache::forget('exam-engine:summary:'.ExamEngineRun::for($exam));

        $this->audit($exam, 'mcq.engine.close', 'Closed the online exam'.($unfinished ? " ({$unfinished} unfinished attempts force-submitted)" : ''), $scope, [
            'unfinished_forced' => $unfinished,
        ]);

        return response()->json([
            'ok'         => true,
            'unfinished' => $unfinished,
            'message'    => 'Exam closed. '.($unfinished
                ? "{$unfinished} unfinished ".($unfinished === 1 ? 'attempt was' : 'attempts were').' submitted with their last saved answers.'
                : 'Every attempt was already submitted.'),
        ]);
    }

    // ── presentation ──────────────────────────────────────────────────────

    private function presentSummary(array $summary, LiveMonitorScope $scope): array
    {
        $counts = (array) ($summary['counts'] ?? []);
        $bySchool = collect((array) ($summary['by_school'] ?? []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'school_id'   => isset($row['school_id']) ? (string) $row['school_id'] : null,
                'school_name' => $row['school_name'] ?? '—',
                'roster'      => (int) ($row['roster'] ?? 0),
                'checked_in'  => (int) ($row['checked_in'] ?? 0),
                'writing'     => (int) ($row['writing'] ?? 0),
                'submitted'   => (int) ($row['submitted'] ?? 0),
                'offline'     => (int) ($row['offline'] ?? 0),
                'flagged'     => (int) ($row['flagged'] ?? 0),
            ]);

        if ($scope->schoolId !== null) {
            // A coordinator sees only their own school's numbers, never the run-wide totals.
            $own = $bySchool->firstWhere('school_id', $scope->schoolId);

            return [
                'state'        => $summary['state'] ?? null,
                'server_time'  => $summary['server_time'] ?? null,
                'pace_seconds' => null,
                'unfinished'   => $own['writing'] ?? 0,
                'counts'       => [
                    'roster'     => $own['roster'] ?? 0,
                    'checked_in' => $own['checked_in'] ?? 0,
                    'waiting'    => null,
                    'writing'    => $own['writing'] ?? 0,
                    'submitted'  => $own['submitted'] ?? 0,
                    'offline'    => $own['offline'] ?? 0,
                    'locked'     => null,
                    'flagged'    => $own['flagged'] ?? 0,
                ],
                'by_school'    => $own ? [$own] : [],
            ];
        }

        return [
            'state'        => $summary['state'] ?? null,
            'server_time'  => $summary['server_time'] ?? null,
            'pace_seconds' => isset($summary['pace_seconds']) ? (int) $summary['pace_seconds'] : null,
            'unfinished'   => isset($summary['unfinished']) ? (int) $summary['unfinished'] : null,
            'counts'       => [
                'roster'     => (int) ($counts['roster'] ?? 0),
                'checked_in' => (int) ($counts['checked_in'] ?? 0),
                'waiting'    => (int) ($counts['waiting'] ?? 0),
                'writing'    => (int) ($counts['writing'] ?? 0),
                'submitted'  => (int) ($counts['submitted'] ?? 0),
                'offline'    => (int) ($counts['offline'] ?? 0),
                'locked'     => (int) ($counts['locked'] ?? 0),
                'flagged'    => (int) $bySchool->sum('flagged'),
            ],
            'by_school'    => $bySchool->sortBy('school_name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }

    private function presentAttempts(array $attempts, ?string $serverTime, LiveMonitorScope $scope, int $page): array
    {
        $rows = collect((array) ($attempts['data'] ?? []))
            ->filter(fn ($row) => is_array($row) && isset($row['reg_id']))
            ->map(fn (array $row) => $this->presentRow($row, $serverTime));

        if ($scope->schoolId !== null) {
            // Defence in depth: never show a row from another school, whatever the engine returns.
            $rows = $rows->filter(fn (array $row) => $row['school_id'] === $scope->schoolId);
        }

        if ($scope->extendCap !== null) {
            $used = $this->coordinatorExtendUsed($rows->pluck('reg_id')->all());
            $rows = $rows->map(fn (array $row) => $row + [
                'extend_used' => $used[$row['reg_id']] ?? 0,
                'extend_left' => max(0, $scope->extendCap - ($used[$row['reg_id']] ?? 0)),
            ]);
        }

        $perPage = max(1, (int) ($attempts['per_page'] ?? self::PER_PAGE));
        $total = (int) ($attempts['total'] ?? $rows->count());

        return [
            'data'      => $rows->values()->all(),
            'total'     => $total,
            'page'      => (int) ($attempts['page'] ?? $page),
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function presentRow(array $row, ?string $serverTime): array
    {
        $state = $row['state'] ?? null;
        $lastSeen = $row['last_seen_at'] ?? null;
        $now = $serverTime ? CarbonImmutable::parse($serverTime) : CarbonImmutable::now();

        return [
            'reg_id'        => (int) $row['reg_id'],
            'name'          => (string) ($row['name'] ?? ''),
            'school_id'     => isset($row['school_id']) ? (string) $row['school_id'] : null,
            'school_name'   => $row['school_name'] ?? null,
            'class_name'    => $row['class_name'] ?? null,
            'hall_ticket'   => isset($row['hall_ticket']) ? (string) $row['hall_ticket'] : null,
            'state'         => $state,
            'checked_in_at' => $row['checked_in_at'] ?? null,
            'started_at'    => $row['started_at'] ?? null,
            'deadline'      => $row['deadline'] ?? null,
            'submitted_at'  => $row['submitted_at'] ?? null,
            'submit_mode'   => $row['submit_mode'] ?? null,
            'last_seen_at'  => $lastSeen,
            'answered'      => (int) ($row['answered'] ?? 0),
            'flags'         => (int) ($row['flags'] ?? 0),
            'device'        => $row['device'] ?? null,
            'ip'            => $row['ip'] ?? null,
            'extra_minutes' => (int) ($row['extra_minutes'] ?? 0),
            'offline'       => $state === 'writing' && $lastSeen && CarbonImmutable::parse($lastSeen)->lt($now->subSeconds(60)),
        ];
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * Minutes school coordinators have already added per candidate (from this monitor's own
     * audit rows, so time given by the Sahodaya or on the roster doesn't count against the cap).
     *
     * @param  list<int>  $regIds
     * @return array<int, int>
     */
    private function coordinatorExtendUsed(array $regIds): array
    {
        if ($regIds === []) {
            return [];
        }

        return AuditLog::where('action', 'mcq.engine.extend')
            ->where('subject_type', (new McqRegistration)->getMorphClass())
            ->whereIn('subject_id', array_map('strval', $regIds))
            ->get(['subject_id', 'properties'])
            ->filter(fn (AuditLog $log) => ($log->properties['via'] ?? null) === 'school')
            ->groupBy('subject_id')
            ->mapWithKeys(fn ($logs, $regId) => [(int) $regId => (int) $logs->sum(fn (AuditLog $log) => (int) ($log->properties['minutes'] ?? 0))])
            ->all();
    }

    private function blockedUnlessArmed(McqExam $exam): ?JsonResponse
    {
        $status = $this->engineStatus($exam);
        if ($status['available'] && $status['state'] === 'armed') {
            return null;
        }

        return response()->json([
            'ok'      => false,
            'message' => $status['closed'] ? 'This exam has been closed on the exam engine.' : $status['message'],
        ], 422);
    }

    private function engineError(ExamEngineException $e): JsonResponse
    {
        // The engine refused (4xx: locked, already submitted, …) → 422; unreachable or failing → 503.
        $status = $e->status !== null && $e->status >= 400 && $e->status < 500 ? 422 : 503;

        return response()->json([
            'ok'      => false,
            'code'    => $e->errorCode,
            'message' => $status === 422 ? $e->getMessage() : 'The exam engine did not answer: '.$e->getMessage(),
        ], $status);
    }

    private function audit(McqExam $exam, string $action, string $description, LiveMonitorScope $scope, array $properties = [], ?McqRegistration $subject = null): void
    {
        app(PlatformAuditLogger::class)->mcq($exam, $action, $description, $properties + [
            'actor' => $scope->actor,
            'via'   => $scope->via,
        ], $subject);
    }
}
