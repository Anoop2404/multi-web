<?php

namespace App\Services\ExamEngine;

use App\Models\McqExam;
use App\Models\McqQuestion;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqExamPasscodeService;
use App\Services\Mcq\McqPaperService;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Arms exams on the exam engine and keeps their roster in step (exam-engine/CONTRACT.md §2).
 *
 * Arming sends the run meta, the roster (with each candidate's bcrypt password hash and an
 * HMAC of their exam passcode) and the paper encrypted with AES-256-GCM. The answer key
 * never leaves Laravel: results come back as events and are graded by ExamEngineEventProcessor.
 */
class ExamEngineBridge
{
    private const ROSTER_CHUNK = 2000;

    public function __construct(
        private readonly ExamEngineClient $client,
        private readonly McqPaperService $papers,
        private readonly McqExamPasscodeService $passcodes,
        private readonly ExamEngineBookingService $bookings,
    ) {}

    /** Sends meta, roster and the encrypted paper, then marks the exam armed. Safe to repeat until the exam starts. */
    public function arm(McqExam $exam): array
    {
        $this->assertArmable($exam);
        $this->bookings->assertCapacity($exam);

        $run = ExamEngineRun::for($exam);
        $version = (int) ($exam->settings_json['engine_bundle_version'] ?? 0) + 1;
        $paper = $this->papers->questionsForExam($exam);

        $this->client->putMeta($run, $this->meta($exam, $paper, $version));

        $registrations = $this->rosterRegistrations($exam);
        foreach ($registrations->chunk(self::ROSTER_CHUNK) as $chunk) {
            $this->client->putRoster($run, $chunk->map(fn (McqRegistration $r) => $this->rosterEntry($r, $exam, $version))->values()->all());
        }

        $this->client->putBundle($run, $this->encryptedBundle($exam, $paper, $version));

        $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], [
            'engine_state'          => 'armed',
            'engine_bundle_version' => $version,
            'engine_armed_at'       => now()->toIso8601String(),
        ])]);
        $this->bookings->sync($exam->fresh(), 'armed');

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.engine.armed', "Armed the online exam on the exam engine ({$registrations->count()} candidates, paper v{$version})", [
            'candidates' => $registrations->count(),
            'questions'  => $paper->count(),
            'version'    => $version,
        ]);

        return ['candidates' => $registrations->count(), 'questions' => $paper->count(), 'version' => $version];
    }

    public function disarm(McqExam $exam): void
    {
        if ($exam->scheduled_at && now()->greaterThanOrEqualTo($exam->scheduled_at)) {
            throw ValidationException::withMessages(['engine' => 'The exam has started, so it can only be closed, not disarmed.']);
        }

        $this->client->disarm(ExamEngineRun::for($exam));

        $settings = $exam->settings_json ?? [];
        unset($settings['engine_state'], $settings['engine_armed_at']);
        $exam->update(['settings_json' => $settings]);
        $this->bookings->sync($exam->fresh(), 'booked');

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.engine.disarmed', 'Disarmed the online exam on the exam engine');
    }

    /** Re-sends the whole roster without touching the paper (late approvals during check-in). */
    public function syncRoster(McqExam $exam): int
    {
        if (ExamEngineRun::state($exam) !== 'armed') {
            return 0;
        }

        $run = ExamEngineRun::for($exam);
        $version = (int) ($exam->settings_json['engine_bundle_version'] ?? 1);
        $registrations = $this->rosterRegistrations($exam);
        foreach ($registrations->chunk(self::ROSTER_CHUNK) as $chunk) {
            $this->client->putRoster($run, $chunk->map(fn (McqRegistration $r) => $this->rosterEntry($r, $exam, $version))->values()->all());
        }
        $this->bookings->sync($exam, 'armed');

        return $registrations->count();
    }

    /** Re-sends one candidate's roster entry (passcode reissue, password change, extra time). No-op unless armed. */
    public function pushRosterEntry(McqRegistration $registration): void
    {
        $registration->loadMissing('exam');
        $exam = $registration->exam;
        if (! $exam || ! ExamEngineRun::usesEngine($exam) || ExamEngineRun::state($exam) !== 'armed') {
            return;
        }

        $version = (int) ($exam->settings_json['engine_bundle_version'] ?? 1);
        $this->client->putRosterEntry(ExamEngineRun::for($exam), $registration->id, $this->rosterEntry($registration, $exam, $version));
    }

    /**
     * HMAC the engine checks a typed passcode against (CONTRACT §5.2). The per-run secret is
     * derived from the app key rather than stored, so it never appears in exam settings.
     */
    public static function passcodeSecret(McqExam $exam): string
    {
        return hash_hmac('sha256', 'exam-engine-passcode|'.ExamEngineRun::for($exam), (string) config('app.key'));
    }

    public static function passcodeHmac(McqExam $exam, int $regId, string $passcode): string
    {
        $normalised = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $passcode));

        return hash_hmac('sha256', "{$regId}|{$normalised}", (string) hex2bin(self::passcodeSecret($exam)));
    }

    /** @return array<string, mixed> */
    public function meta(McqExam $exam, Collection $paper, int $version): array
    {
        $settings = ExamEngineRun::settings($exam);
        $startsAt = $exam->scheduled_at->copy()->utc();
        $sahodaya = Tenant::find($exam->tenant_id);

        return [
            'run'                            => ExamEngineRun::for($exam),
            'tenant_id'                      => $exam->tenant_id,
            'exam_id'                        => $exam->id,
            'sahodaya_code'                  => self::sahodayaCode($exam->tenant_id),
            'sahodaya_name'                  => $sahodaya?->name,
            'logo_url'                       => $sahodaya ? TenantBranding::logoUrl($sahodaya) : null,
            'title'                          => $exam->title,
            'timing'                         => $settings['timing'] === 'individual' ? 'individual' : 'fixed',
            'checkin_opens_at'               => self::iso($startsAt->copy()->subHours((int) $settings['checkin_hours'])),
            'waiting_opens_at'               => self::iso($startsAt->copy()->subMinutes((int) $settings['waiting_room_minutes'])),
            'starts_at'                      => self::iso($startsAt),
            'duration_minutes'               => (int) ($exam->duration_minutes ?: 60),
            'late_entry_minutes'             => (int) $settings['late_entry_minutes'],
            'grace_seconds'                  => (int) $settings['grace_seconds'],
            'entry_mode'                     => $settings['entry_mode'],
            'supervision'                    => $settings['supervision'],
            'start_pin'                      => $settings['supervision'] === 'supervised' ? (filled($settings['start_pin'] ?? null) ? (string) $settings['start_pin'] : null) : null,
            'show_score_on_submit'           => (bool) $settings['show_score_on_submit'],
            'shuffle_questions'              => (bool) ($exam->settings_json['shuffle_questions'] ?? false),
            'device_idle_minutes'            => (int) $settings['device_idle_minutes'],
            'sync_interval_seconds'          => (int) $settings['sync_interval_seconds'],
            'heartbeat_seconds'              => (int) $settings['heartbeat_seconds'],
            'coordinator_extend_cap_minutes' => (int) $settings['coordinator_extend_cap_minutes'],
            'passcode_secret'                => self::passcodeSecret($exam),
            'question_count'                 => $paper->count(),
            'max_score'                      => round($paper->filter->isGradable()->sum(fn (McqQuestion $q) => $q->marksValue()), 2),
            // id → type, so the engine can validate answer shapes without seeing the paper (NOTES-engine.md).
            'questions'                      => (object) $paper->mapWithKeys(fn (McqQuestion $q) => [(string) $q->id => $q->typeValue()])->all(),
            'bundle_version'                 => $version,
        ];
    }

    /** @return array<string, mixed> */
    public function rosterEntry(McqRegistration $registration, McqExam $exam, int $version): array
    {
        $registration->loadMissing(['student.user', 'teacher.user', 'school:id,name', 'student.schoolClass:id,name']);
        $user = $registration->student?->user ?? $registration->teacher?->user;
        $user = $user instanceof User ? $user : null;

        $passcode = $registration->hall_ticket_no ? $this->passcodes->ensure($registration) : null;

        return [
            'reg_id'        => $registration->id,
            'name'          => $registration->student?->name ?? $registration->teacher?->name ?? 'Candidate',
            'school_id'     => $registration->school_id,
            'school_name'   => $registration->school?->name,
            'class_name'    => $registration->student?->schoolClass?->name ?? ($registration->teacher_id ? 'Teacher' : null),
            'hall_ticket'   => $registration->hall_ticket_no,
            'username'      => $user?->username ? mb_strtolower($user->username) : null,
            'email'         => $user?->email ? mb_strtolower($user->email) : null,
            'password_hash' => $user?->getAuthPassword() ?: null,
            'passcode_hmac' => $passcode ? self::passcodeHmac($exam, $registration->id, $passcode) : null,
            'extra_minutes' => (int) ($registration->extra_time_minutes ?? 0),
            'allowed'       => ! $registration->isCancelled() && ! $registration->blocksScoring(),
        ];
    }

    /** Approved, active registrations (students and teachers) that may sit the exam. */
    public function rosterRegistrations(McqExam $exam): Collection
    {
        return McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->where('approval_status', 'approved')
            ->with(['student.user', 'teacher.user', 'school:id,name', 'student.schoolClass:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * The paper as the AES-256-GCM bundle the engine serves (CONTRACT §2.3). Images are embedded
     * as data URIs so the whole paper is one immutable, CDN-cacheable file.
     *
     * @return array{version: int, iv: string, ciphertext: string, key: string, sha256: string}
     */
    public function encryptedBundle(McqExam $exam, Collection $paper, int $version): array
    {
        $sections = $this->sectionTitles($exam);
        $plain = json_encode([
            'version'   => $version,
            'questions' => $paper->values()->map(fn (McqQuestion $q) => [
                'id'            => $q->id,
                'type'          => $q->typeValue(),
                'section'       => $sections[$q->bank_id] ?? null,
                'body'          => (string) ($q->body_text ?? ''),
                'image'         => $this->dataUri($q->document_path),
                'options'       => $q->typeValue() === McqQuestion::TYPE_NUMERIC ? [] : collect($q->options_json ?? [])->map(fn ($o) => [
                    'key'   => strtolower((string) ($o['key'] ?? '')),
                    'label' => (string) ($o['label'] ?? ''),
                    'image' => $this->dataUri($o['image_path'] ?? null),
                ])->values()->all(),
                'show_count'    => $q->showsCorrectCount() ? count($q->correctKeys()) : null,
                'marks'         => $q->marksValue(),
                'negative_mark' => $q->negativeMarkValue(),
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $key = random_bytes(32);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new ExamEngineException('Could not encrypt the exam paper.');
        }
        $sealed = $ciphertext.$tag;

        return [
            'version'    => $version,
            'iv'         => base64_encode($iv),
            'ciphertext' => base64_encode($sealed),
            'key'        => base64_encode($key),
            'sha256'     => hash('sha256', $sealed),
        ];
    }

    public static function sahodayaCode(string $tenantId): string
    {
        $prefix = SahodayaProfile::where('tenant_id', $tenantId)->value('prefix');
        if (filled($prefix)) {
            return strtoupper((string) $prefix);
        }

        return strtoupper((string) (Tenant::find($tenantId)?->subdomain ?: substr($tenantId, 0, 8)));
    }

    /** The exam site address students use for this exam's Sahodaya. */
    public static function examSiteUrl(McqExam $exam): string
    {
        return rtrim((string) config('exam_engine.public_url'), '/').'/'.rawurlencode(self::sahodayaCode($exam->tenant_id));
    }

    private function assertArmable(McqExam $exam): void
    {
        $problems = [];
        if (! ExamEngineRun::usesEngine($exam)) {
            $problems[] = 'Turn on "Use the online exam engine" (the exam must be online).';
        }
        if (! in_array($exam->status, ['published', 'ongoing'], true)) {
            $problems[] = 'Publish the exam first.';
        }
        if (! $exam->scheduled_at) {
            $problems[] = 'Set the exam date and time.';
        } elseif (ExamEngineRun::state($exam) === 'armed' && now()->greaterThanOrEqualTo($exam->scheduled_at)) {
            $problems[] = 'The exam has already started, so it can\'t be re-armed.';
        }
        if (ExamEngineRun::state($exam) === 'closed') {
            $problems[] = 'This exam was closed on the engine.';
        }

        $paper = $this->papers->questionsForExam($exam);
        if ($paper->isEmpty()) {
            $problems[] = 'The paper has no questions (Paper tab).';
        } elseif ($paper->contains(fn (McqQuestion $q) => ! $q->isGradable())) {
            $problems[] = 'Every question on the paper needs an answer key.';
        }
        $settings = ExamEngineRun::settings($exam);
        if ($settings['supervision'] === 'supervised' && blank($settings['start_pin'] ?? null)) {
            $problems[] = 'Supervised exams need a start PIN.';
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['engine' => $problems]);
        }
    }

    /** @return array<int, ?string> bank id → this exam's section heading */
    private function sectionTitles(McqExam $exam): array
    {
        return \Illuminate\Support\Facades\DB::table('mcq_exam_question_banks')
            ->where('exam_id', $exam->id)
            ->whereNotNull('section_title')
            ->pluck('section_title', 'bank_id')
            ->all();
    }

    private function dataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $bytes = TenantStorage::get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }
        if (strlen($bytes) > (int) config('exam_engine.max_image_bytes')) {
            throw ValidationException::withMessages(['engine' => 'An image on the paper is larger than '.round(config('exam_engine.max_image_bytes') / 1024).' KB. Replace it with a smaller one.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private static function iso(CarbonInterface $at): string
    {
        return $at->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
