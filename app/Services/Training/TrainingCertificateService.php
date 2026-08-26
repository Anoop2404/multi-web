<?php

namespace App\Services\Training;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use App\Services\Notifications\NotificationService;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrainingCertificateService
{
    public function assertEligible(TrainingRegistration $registration): void
    {
        $registration->loadMissing(['program.sessions']);

        if ($registration->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'registration' => 'Registration must be confirmed before issuing a certificate.',
            ]);
        }

        $present = $this->presentDaysCount($registration);
        $required = $this->requiredPresentDays($registration->program);

        if ($present < $required) {
            $message = $required <= 1
                ? 'At least one training day must be marked present before issuing a certificate.'
                : "Attendance requirement not met: {$present}/{$required} day(s) present.";

            throw ValidationException::withMessages([
                'attendance' => $message,
            ]);
        }
    }

    /** Days that must be marked present (from min_attendance_percent, or at least 1). */
    public function requiredPresentDays(?TrainingProgram $program): int
    {
        if (! $program) {
            return 1;
        }

        $percent = $program->min_attendance_percent;
        $totalDays = max(1, $program->dayCount());

        if ($percent === null || (int) $percent <= 0) {
            return 1;
        }

        return max(1, (int) ceil($totalDays * ((int) $percent) / 100));
    }

    public function issue(TrainingRegistration $registration, bool $notify = true): Certificate
    {
        $this->assertEligible($registration);
        $registration->load(['program', 'teacher', 'school']);

        $existing = Certificate::where('entity_type', TrainingRegistration::class)
            ->where('entity_id', $registration->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $template = $this->resolveTemplate($registration);
        $certType = $this->resolveCertificateType($registration);

        $certificate = Certificate::create([
            'entity_type' => TrainingRegistration::class,
            'entity_id' => $registration->id,
            'template_id' => $template?->id,
            'cert_type' => $certType,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(),
        ]);

        if ($notify) {
            $this->notifyCertificateAvailable($registration, $certificate);
        }

        return $certificate;
    }

    public function resolveCertificateType(TrainingRegistration $registration): string
    {
        $type = $registration->program?->certificate_type ?: 'participation';

        return in_array($type, TrainingProgram::CERTIFICATE_TYPES, true)
            ? $type
            : 'participation';
    }

    public function resolveTemplate(TrainingRegistration $registration): ?CertificateTemplate
    {
        $program = $registration->program;
        if (! $program) {
            return null;
        }

        if ($program->certificate_template_id) {
            // An explicit per-program pick always wins, regardless of the template's
            // is_active flag — that flag only governs which template a program picks up
            // implicitly by certificate_type below. Requiring is_active here too meant a
            // program could point at a specific template and still silently fall through to
            // a different one the moment that template wasn't the platform-wide active one.
            $chosen = CertificateTemplate::query()
                ->where('tenant_id', $program->tenant_id)
                ->where('event_type', 'training')
                ->whereKey($program->certificate_template_id)
                ->first();

            if ($chosen) {
                return $chosen;
            }
        }

        $certType = $this->resolveCertificateType($registration);

        $template = CertificateTemplate::where('tenant_id', $program->tenant_id)
            ->where('event_type', 'training')
            ->where('certificate_type', $certType)
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($template || $certType === 'participation') {
            return $template;
        }

        // Fall back to participation template when the specific type has none.
        return CertificateTemplate::where('tenant_id', $program->tenant_id)
            ->where('event_type', 'training')
            ->where('certificate_type', 'participation')
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /** @return array<string, string> */
    public function resolveFieldValues(TrainingRegistration $registration, Tenant $sahodaya): array
    {
        $registration->loadMissing(['program.sessions', 'teacher', 'school']);

        $presentSessions = $this->presentSessions($registration);
        $conductedOn = $this->formatConductedDates($presentSessions, $registration->program);
        $daysAttended = $presentSessions->count();
        $totalDays = $registration->program?->dayCount() ?: $daysAttended;
        $trainingHours = $this->resolveTrainingHours($registration, $presentSessions);

        $venue = $registration->program?->venue
            ?? $presentSessions->first()?->venue
            ?? '';

        $nameFields = self::recipientNameFields(
            $registration->teacher?->name,
            $registration->teacher?->gender,
        );

        $defaults = [
            'salutation' => $nameFields['salutation'],
            'recipient_name' => $nameFields['recipient_name'],
            'recipient_with_title' => $nameFields['recipient_with_title'],
            'program_title' => $registration->program?->title ?? '',
            'sahodaya_name' => strtoupper($sahodaya->name),
            'conducted_on' => $conductedOn,
            'designation' => $registration->teacher?->designation ?? '',
            'school_name' => $registration->displaySchoolName() === '—'
                ? ''
                : $registration->displaySchoolName(),
            'venue' => $venue,
            'days_attended' => (string) $daysAttended,
            'total_days' => (string) $totalDays,
            'training_hours' => (string) $trainingHours,
            'certificate_date' => now()->format('j F Y'),
        ];

        $template = $this->resolveTemplate($registration);
        $fields = $template?->dynamic_fields_json ?? [];

        if (! is_array($fields) || $fields === []) {
            return $defaults;
        }

        $resolved = [];
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }
            $source = $field['source'] ?? $key;
            $resolved[$key] = $defaults[$source] ?? ($field['default'] ?? '');
        }

        return array_merge($defaults, $resolved);
    }

    public static function toAbsoluteUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
            return $url;
        }

        return url($url);
    }

    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, backgroundUrl: ?string, overlayLayout: array} */
    public function renderContext(TrainingRegistration $registration, Tenant $sahodaya): array
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $template = $this->resolveTemplate($registration);
        $fieldValues = $this->resolveFieldValues($registration, $sahodaya);

        $logoUrl = self::toAbsoluteUrl(
            $template?->logo_path
                ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
                : TenantBranding::logoUrl($sahodaya)
        );

        $sealUrl = self::toAbsoluteUrl(
            $template?->seal_path
                ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
                : null
        );

        $backgroundUrl = self::toAbsoluteUrl(
            $template?->background_path
                ? TenantStorage::logoUrl($sahodaya, $template->background_path)
                : null
        );

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name' => $s['name'] ?? '',
                'designation' => $s['designation'] ?? '',
                'signature_url' => self::toAbsoluteUrl(! empty($s['signature_path'])
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null),
            ])->values()->all();

        return compact('template', 'fieldValues', 'logoUrl', 'sealUrl', 'signatories', 'backgroundUrl', 'overlayLayout');
    }

    /** Demo certificate context for client previews (no real registration). */
    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, backgroundUrl: ?string, overlayLayout: array, certificate: object} */
    public function sampleRenderContext(TrainingProgram $program, Tenant $sahodaya, ?int $templateId = null): array
    {
        $template = $this->resolveSampleTemplate(
            $sahodaya,
            $templateId ?? $program->certificate_template_id,
            $program->certificate_type,
        );

        $conductedOn = $program->start_date?->format('j F Y') ?? '11 July 2026';
        if ($program->start_date && $program->end_date && ! $program->start_date->isSameDay($program->end_date)) {
            $conductedOn = $program->start_date->format('j F Y').' – '.$program->end_date->format('j F Y');
        }

        return $this->buildSampleContext($template, $sahodaya, array_merge(
            self::recipientNameFields('Sample Teacher', 'female'),
            [
                'designation' => 'PGT Mathematics',
                'school_name' => 'Sample Model School',
                'program_title' => $program->title,
                'sahodaya_name' => strtoupper($sahodaya->name),
                'conducted_on' => $conductedOn,
                'venue' => $program->venue ?? 'St. Alphonsa Public School, Oorakam',
                'days_attended' => '1',
                'total_days' => (string) max(1, $program->dayCount() ?: 1),
                'training_hours' => '6',
                'certificate_date' => now()->format('j F Y'),
            ],
        ));
    }

    /** Preview a saved training template with sample recipient data (no program required). */
    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, backgroundUrl: ?string, overlayLayout: array, certificate: object} */
    public function sampleRenderContextForTemplate(CertificateTemplate $template, Tenant $sahodaya): array
    {
        return $this->buildSampleContext($template, $sahodaya, array_merge(
            self::recipientNameFields('Sample Teacher', 'female'),
            [
                'designation' => 'PGT Mathematics',
                'school_name' => 'Sample Model School',
                'program_title' => $template->title ?: 'Sample Training Program',
                'sahodaya_name' => strtoupper($sahodaya->name),
                'conducted_on' => now()->format('j F Y'),
                'venue' => 'Sample Venue',
                'days_attended' => '1',
                'total_days' => '1',
                'training_hours' => '6',
                'certificate_date' => now()->format('j F Y'),
            ],
        ));
    }

    /**
     * Resolve Mr./Mrs. (and plain name) from teacher gender for certificate text.
     *
     * @return array{salutation: string, recipient_name: string, recipient_with_title: string}
     */
    public static function recipientNameFields(?string $name, ?string $gender): array
    {
        $raw = trim((string) $name);
        $stripped = trim((string) preg_replace('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '', $raw));
        $salutation = self::salutationForGender($gender);

        return [
            'salutation' => $salutation,
            'recipient_name' => $stripped,
            'recipient_with_title' => trim($salutation.' '.$stripped),
        ];
    }

    public static function salutationForGender(?string $gender): string
    {
        return match (strtolower(trim((string) $gender))) {
            'male', 'm' => 'Mr.',
            'female', 'f' => 'Mrs.',
            default => 'Mr./Ms.',
        };
    }

    private function resolveSampleTemplate(Tenant $sahodaya, ?int $templateId, ?string $certificateType): ?CertificateTemplate
    {
        if ($templateId) {
            // Same reasoning as resolveTemplate(): an explicit template_id (from the
            // program's saved choice, or the ?template_id= preview override) should preview
            // exactly that template, active or not.
            $template = CertificateTemplate::query()
                ->where('tenant_id', $sahodaya->id)
                ->where('event_type', 'training')
                ->whereKey($templateId)
                ->first();
            if ($template) {
                return $template;
            }
        }

        $certType = in_array($certificateType, TrainingProgram::CERTIFICATE_TYPES, true)
            ? $certificateType
            : 'participation';

        $template = CertificateTemplate::where('tenant_id', $sahodaya->id)
            ->where('event_type', 'training')
            ->where('certificate_type', $certType)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $template && $certType !== 'participation') {
            $template = CertificateTemplate::where('tenant_id', $sahodaya->id)
                ->where('event_type', 'training')
                ->where('certificate_type', 'participation')
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        return $template;
    }

    /**
     * @param  array<string, string>  $fieldValues
     * @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, backgroundUrl: ?string, overlayLayout: array, certificate: object}
     */
    private function buildSampleContext(?CertificateTemplate $template, Tenant $sahodaya, array $fieldValues): array
    {
        $logoUrl = self::toAbsoluteUrl(
            $template?->logo_path
                ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
                : TenantBranding::logoUrl($sahodaya)
        );

        $sealUrl = self::toAbsoluteUrl(
            $template?->seal_path
                ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
                : null
        );

        $backgroundUrl = self::toAbsoluteUrl(
            $template?->background_path
                ? TenantStorage::logoUrl($sahodaya, $template->background_path)
                : null
        );

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name' => $s['name'] ?? '',
                'designation' => $s['designation'] ?? '',
                'signature_url' => self::toAbsoluteUrl(! empty($s['signature_path'])
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null),
            ])->values()->all();

        $certificate = (object) [
            'verification_uuid' => 'SAMPLE-DEMO-0000',
        ];

        return compact('template', 'fieldValues', 'logoUrl', 'sealUrl', 'signatories', 'backgroundUrl', 'overlayLayout', 'certificate');
    }

    public function presentDaysCount(TrainingRegistration $registration): int
    {
        return $this->presentSessions($registration)->count();
    }

    private function notifyCertificateAvailable(TrainingRegistration $registration, Certificate $certificate): void
    {
        $registration->loadMissing(['teacher', 'program', 'school']);
        $teacher = $registration->teacher;
        if (! $teacher) {
            return;
        }

        $programTitle = $registration->program?->title ?? 'Training';
        $printUrl = route('certificates.print', $certificate->verification_uuid, absolute: true);

        // Portal in-app card, only meaningful for teachers who have a linked
        // portal login — keeps the existing bell-notification behavior.
        $teacherUser = $teacher->user_id ? User::find($teacher->user_id) : null;
        if ($teacherUser) {
            $schoolId = $registration->school_id;
            $actionUrl = $schoolId
                ? "/portal/teacher/{$schoolId}/training/{$registration->id}/certificate"
                : $printUrl;

            app(NotificationService::class)->notifyFromTemplate(
                $teacherUser,
                'training.certificate.available',
                [
                    'program_title' => $programTitle,
                    'teacher_name' => $teacher->name ?? '',
                ],
                $actionUrl,
            );
        }

        // Always also email the teacher directly at their registered email —
        // this is the path that actually reaches most training participants,
        // since QR self-registered teachers rarely have a portal account. Uses
        if ($teacher->email) {
            $this->emailCertificatePdf($registration, $certificate, $programTitle, $printUrl);
        }
    }

    /**
     * Serve a certificate's cached PDF when available, else render fresh and persist it
     * for next time. Unlike the Fest side's read-only-on-miss equivalent, this writes
     * back on a miss — Training has no separate bulk "render & cache" action, so the
     * per-teacher PDF/email links are the only render trigger there is.
     */
    public function cachedOrFreshPdf(TrainingRegistration $registration, Certificate $certificate, Tenant $sahodaya): string
    {
        if ($certificate->file_path && ! $certificate->is_stale) {
            $cached = TenantStorage::get($certificate->file_path, $certificate->storage_disk);
            if ($cached !== null) {
                return $cached;
            }
        }

        $registration->loadMissing(['program', 'teacher', 'school']);
        $render = $this->renderContext($registration, $sahodaya);
        $fieldValues = $this->resolveFieldValues($registration, $sahodaya);

        $html = view('training.certificate', array_merge($render, [
            'registration' => $registration,
            'certificate' => $certificate,
            'sahodaya' => $sahodaya,
            'fieldValues' => $fieldValues,
            'isPdf' => true,
        ]))->render();

        $pdf = PdfGenerator::render($html, true);

        $this->persistRenderedPdf($certificate, $sahodaya, $pdf, array_merge($render, ['fieldValues' => $fieldValues]));

        return $pdf;
    }

    /** Best-effort cache write — a storage hiccup must degrade to "served, not cached," never fail the request that already has a good PDF in hand. */
    private function persistRenderedPdf(Certificate $certificate, Tenant $sahodaya, string $pdf, array $context): void
    {
        if (! $certificate->exists) {
            return;
        }

        $path = 'certificates/'.$sahodaya->id.'/training/'.$certificate->entity_id.'/'.$certificate->id.'-'.$certificate->verification_uuid.'.pdf';
        $disk = TenantStorage::uploadDisk();

        try {
            TenantStorage::put($path, $pdf, $disk);
        } catch (\Throwable $e) {
            Log::warning("Could not cache training certificate PDF for certificate {$certificate->id}: ".$e->getMessage());

            return;
        }

        $certificate->update([
            'file_path' => $path,
            'storage_disk' => $disk,
            'content_hash' => $this->contentHash($context),
            'is_stale' => false,
            'stale_checked_at' => now(),
            'rendered_at' => now(),
        ]);
    }

    /** Structural mirror of FestCertificateService::contentHash() — sha256 of the resolved render inputs, excluding certificate_date (recomputed fresh every call, would otherwise change the hash daily for no real content change). */
    public function contentHash(array $context): string
    {
        $fieldValues = $context['fieldValues'] ?? [];
        unset($fieldValues['certificate_date']);

        $template = $context['template'] ?? null;

        return hash('sha256', json_encode([
            'template_id' => $template?->id,
            'template_updated_at' => $template?->updated_at?->toISOString(),
            'fieldValues' => $fieldValues,
        ]));
    }

    public function downloadPdfResponse(TrainingRegistration $registration, Tenant $sahodaya)
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $certificate = Certificate::where('entity_type', TrainingRegistration::class)
            ->where('entity_id', $registration->id)
            ->first();

        if (! $certificate) {
            $certificate = $this->issue($registration);
        }

        $pdf = $this->cachedOrFreshPdf($registration, $certificate, $sahodaya);
        $filename = Str::slug($registration->teacher?->name ?? 'certificate').'-training-certificate.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendCertificateEmailToRegistration(TrainingRegistration $registration, Tenant $sahodaya, ?string $overrideEmail = null): bool
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $targetEmail = $overrideEmail ?: $registration->teacher?->email;

        if (! $targetEmail) {
            return false;
        }

        $isTest = ! empty($overrideEmail);
        $certificate = null;

        if ($registration->exists) {
            $certificate = Certificate::where('entity_type', TrainingRegistration::class)
                ->where('entity_id', $registration->id)
                ->first();
        }

        if (! $certificate) {
            if ($isTest) {
                $certificate = new Certificate([
                    'verification_uuid' => (string) Str::uuid(),
                ]);
            } else {
                try {
                    // notify: false — this method is itself about to send the certificate
                    // email; without this, issue() -> notifyCertificateAvailable() ->
                    // emailCertificatePdf() recurses back into this same method and sends
                    // a first email before this (outer) call sends its own second one.
                    $certificate = $this->issue($registration, notify: false);
                } catch (\Throwable $e) {
                    return false;
                }
            }
        }

        $programTitle = $registration->program?->title ?? 'Training Program';
        $printUrl = url("/certificates/verify/{$certificate->verification_uuid}");

        $isTest = ! empty($overrideEmail);
        $recipientName = $registration->teacher?->name ?? 'Participant';
        $subjectPrefix = $isTest ? '[TEST CERTIFICATE EMAIL] ' : '';
        $subject = "{$subjectPrefix}Your Certificate for {$programTitle}";

        $attachmentName = Str::slug($recipientName).'-certificate.pdf';
        $attachment = [
            'content' => $this->cachedOrFreshPdf($registration, $certificate, $sahodaya),
            'name' => $attachmentName,
            'mime' => 'application/pdf',
        ];

        $viewData = [
            'subject' => $subject,
            'recipientName' => $recipientName,
            'programTitle' => $programTitle,
            'printUrl' => $printUrl,
            'certificate' => $certificate,
            'sahodayaName' => $sahodaya->name,
            'headerTitle' => $sahodaya->name,
            'headerSubtitle' => 'Training Program Certificate',
            'logoUrl' => TenantBranding::logoUrl($sahodaya),
            'portalUrl' => url('/'),
        ];

        try {
            $mailer = SahodayaMailer::for($sahodaya->id);
            if ($mailer->isConfigured()) {
                $mailer->sendViewWithAttachments(
                    $targetEmail,
                    $subject,
                    'emails.training-certificate',
                    $viewData,
                    [$attachment],
                );
            } else {
                Mail::send('emails.training-certificate', $viewData, function ($message) use ($targetEmail, $subject, $attachment) {
                    $message->to($targetEmail)->subject($subject)
                        ->attachData($attachment['content'], $attachment['name'], ['mime' => $attachment['mime']]);
                });
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send certificate email to {$targetEmail}: ".$e->getMessage());

            return false;
        }

        if (! $isTest && $certificate) {
            $certificate->update(['email_sent_at' => now()]);
        }

        return true;
    }

    public function sendTestCertificateEmail(TrainingProgram $program, Tenant $sahodaya, string $testEmail): bool
    {
        $sampleRegistration = TrainingRegistration::where('program_id', $program->id)
            ->where('status', 'confirmed')
            ->with(['teacher', 'school'])
            ->first();

        if (! $sampleRegistration) {
            $sampleRegistration = new TrainingRegistration([
                'program_id' => $program->id,
                'status' => 'confirmed',
            ]);
            $sampleRegistration->setRelation('program', $program);
            $sampleRegistration->setRelation('teacher', (object) [
                'name' => 'Sample Teacher Name',
                'email' => $testEmail,
                'designation' => 'Senior Teacher',
            ]);
            $sampleRegistration->setRelation('school', (object) [
                'name' => 'Sample Model School',
            ]);
        }

        return $this->sendCertificateEmailToRegistration($sampleRegistration, $sahodaya, $testEmail);
    }

    /**
     * Email the teacher a ready-to-open PDF of their certificate (not just a
     * link) — generated from the same view used for print/preview, so it's
     * always pixel-identical to what they'd see on the print page.
     */
    private function emailCertificatePdf(TrainingRegistration $registration, Certificate $certificate, string $programTitle, string $printUrl): void
    {
        $sahodaya = Tenant::find($registration->program?->tenant_id);
        if ($sahodaya) {
            $this->sendCertificateEmailToRegistration($registration, $sahodaya);
        }
    }

    /**
     * Prefer CPD hours from present session durations; fall back to days × assumed day length.
     *
     * @param  Collection<int, TrainingSession>  $presentSessions
     */
    private function resolveTrainingHours(TrainingRegistration $registration, $presentSessions): float
    {
        $hours = app(TrainingCpdService::class)->hoursForRegistration($registration->id);
        if ($hours > 0) {
            return $hours;
        }

        $minutes = $presentSessions->sum(fn ($s) => (int) ($s->duration_minutes ?? 0));
        if ($minutes > 0) {
            return round($minutes / 60, 2);
        }

        return 0.0;
    }

    /** @return Collection<int, TrainingSession> */
    private function presentSessions(TrainingRegistration $registration)
    {
        $registration->loadMissing('program.sessions');
        $sessions = $registration->program?->sessions ?? collect();

        if ($sessions->isEmpty()) {
            return collect();
        }

        $presentSessionIds = TrainingAttendance::where('registration_id', $registration->id)
            ->whereIn('status', TrainingAttendance::PRESENT_LIKE)
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', 'approved');
            })
            ->pluck('session_id');

        return $sessions->whereIn('id', $presentSessionIds)->sortBy('scheduled_at')->values();
    }

    /** @param  Collection<int, TrainingSession>  $presentSessions */
    private function formatConductedDates($presentSessions, ?TrainingProgram $program): string
    {
        if ($presentSessions->isNotEmpty()) {
            return $presentSessions
                ->map(fn ($s) => $s->scheduled_at?->format('j F Y'))
                ->filter()
                ->unique()
                ->join(', ');
        }

        if ($program?->start_date) {
            if ($program->end_date && ! $program->start_date->isSameDay($program->end_date)) {
                return $program->start_date->format('j F Y').' – '.$program->end_date->format('j F Y');
            }

            return $program->start_date->format('j F Y');
        }

        return now()->format('j F Y');
    }
}
