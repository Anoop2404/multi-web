<?php

namespace App\Services\Training;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
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

    public function issue(TrainingRegistration $registration): Certificate
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
            'entity_type'       => TrainingRegistration::class,
            'entity_id'         => $registration->id,
            'template_id'       => $template?->id,
            'cert_type'         => $certType,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at'      => now(),
        ]);

        $this->notifyCertificateAvailable($registration, $certificate);

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
            $chosen = CertificateTemplate::query()
                ->where('tenant_id', $program->tenant_id)
                ->where('event_type', 'training')
                ->whereKey($program->certificate_template_id)
                ->where('is_active', true)
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
            'salutation'           => $nameFields['salutation'],
            'recipient_name'       => $nameFields['recipient_name'],
            'recipient_with_title' => $nameFields['recipient_with_title'],
            'program_title'        => $registration->program?->title ?? '',
            'sahodaya_name'        => strtoupper($sahodaya->name),
            'conducted_on'         => $conductedOn,
            'designation'          => $registration->teacher?->designation ?? '',
            'school_name'          => $registration->displaySchoolName() === '—'
                ? ''
                : $registration->displaySchoolName(),
            'venue'                => $venue,
            'days_attended'        => (string) $daysAttended,
            'total_days'           => (string) $totalDays,
            'training_hours'       => (string) $trainingHours,
            'certificate_date'     => now()->format('j F Y'),
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

    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, backgroundUrl: ?string, overlayLayout: array} */
    public function renderContext(TrainingRegistration $registration, Tenant $sahodaya): array
    {
        $registration->loadMissing(['program', 'teacher', 'school']);
        $template = $this->resolveTemplate($registration);
        $fieldValues = $this->resolveFieldValues($registration, $sahodaya);

        $logoUrl = $template?->logo_path
            ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
            : TenantBranding::logoUrl($sahodaya);

        $sealUrl = $template?->seal_path
            ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
            : null;

        $backgroundUrl = $template?->background_path
            ? TenantStorage::logoUrl($sahodaya, $template->background_path)
            : null;

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'           => $s['name'] ?? '',
                'designation'    => $s['designation'] ?? '',
                'signature_url'  => ! empty($s['signature_path'])
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null,
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
                'designation'      => 'PGT Mathematics',
                'school_name'      => 'Sample Model School',
                'program_title'    => $program->title,
                'sahodaya_name'    => strtoupper($sahodaya->name),
                'conducted_on'     => $conductedOn,
                'venue'            => $program->venue ?? 'St. Alphonsa Public School, Oorakam',
                'days_attended'    => '1',
                'total_days'       => (string) max(1, $program->dayCount() ?: 1),
                'training_hours'   => '6',
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
                'designation'      => 'PGT Mathematics',
                'school_name'      => 'Sample Model School',
                'program_title'    => $template->title ?: 'Sample Training Program',
                'sahodaya_name'    => strtoupper($sahodaya->name),
                'conducted_on'     => now()->format('j F Y'),
                'venue'            => 'Sample Venue',
                'days_attended'    => '1',
                'total_days'       => '1',
                'training_hours'   => '6',
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
            'salutation'           => $salutation,
            'recipient_name'       => $stripped,
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
            $template = CertificateTemplate::query()
                ->where('tenant_id', $sahodaya->id)
                ->where('event_type', 'training')
                ->whereKey($templateId)
                ->where('is_active', true)
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
        $logoUrl = $template?->logo_path
            ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
            : TenantBranding::logoUrl($sahodaya);

        $sealUrl = $template?->seal_path
            ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
            : null;

        $backgroundUrl = $template?->background_path
            ? TenantStorage::logoUrl($sahodaya, $template->background_path)
            : null;

        $overlayLayout = $template?->overlayLayout() ?? CertificateTemplate::defaultBackgroundLayout();

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'          => $s['name'] ?? '',
                'designation'   => $s['designation'] ?? '',
                'signature_url' => ! empty($s['signature_path'])
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null,
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
                    'teacher_name'  => $teacher->name ?? '',
                ],
                $actionUrl,
            );
        }

        // Always also email the teacher directly at their registered email —
        // this is the path that actually reaches most training participants,
        // since QR self-registered teachers rarely have a portal account. Uses
        // the public, no-login certificate print link so it works regardless.
        if ($teacher->email) {
            $this->emailCertificatePdf($registration, $certificate, $programTitle, $printUrl);
        }
    }

    /**
     * Email the teacher a ready-to-open PDF of their certificate (not just a
     * link) — generated from the same view used for print/preview, so it's
     * always pixel-identical to what they'd see on the print page.
     */
    private function emailCertificatePdf(TrainingRegistration $registration, Certificate $certificate, string $programTitle, string $printUrl): void
    {
        $teacher = $registration->teacher;
        $sahodayaId = $registration->program?->tenant_id;
        if (! $teacher?->email || ! $sahodayaId) {
            return;
        }

        try {
            $sahodaya = Tenant::find($sahodayaId);
            if (! $sahodaya) {
                return;
            }

            $render = $this->renderContext($registration, $sahodaya);
            $fieldValues = $this->resolveFieldValues($registration, $sahodaya);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('training.certificate', array_merge($render, [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $sahodaya,
                'fieldValues'  => $fieldValues,
            ]))->setPaper('a4', 'landscape');

            $subject = "Your certificate for {$programTitle} is ready";
            $body = "Dear {$teacher->name},\n\nYour certificate for \"{$programTitle}\" is attached as a PDF.\n\nYou can also view or reprint it anytime here: {$printUrl}";
            $attachment = [
                'content' => $pdf->output(),
                'name'    => 'certificate.pdf',
                'mime'    => 'application/pdf',
            ];

            $mailer = \App\Services\Mail\SahodayaMailer::for($sahodayaId);
            if ($mailer->isConfigured()) {
                $mailer->sendViewWithAttachments(
                    $teacher->email,
                    $subject,
                    'emails.notification-plain',
                    ['title' => $subject, 'body' => $body],
                    [$attachment],
                );

                return;
            }

            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($teacher, $subject, $attachment) {
                $message->to($teacher->email)->subject($subject)
                    ->attachData($attachment['content'], $attachment['name'], ['mime' => $attachment['mime']]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Certificate PDF email failed', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prefer CPD hours from present session durations; fall back to days × assumed day length.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\TrainingSession>  $presentSessions
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

    /** @return \Illuminate\Support\Collection<int, \App\Models\TrainingSession> */
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

    /** @param  \Illuminate\Support\Collection<int, \App\Models\TrainingSession>  $presentSessions */
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
