<?php

namespace App\Services\Training;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
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

        if ($this->presentDaysCount($registration) < 1) {
            throw ValidationException::withMessages([
                'attendance' => 'At least one training day must be marked present before issuing a certificate.',
            ]);
        }
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

        return Certificate::create([
            'entity_type'       => TrainingRegistration::class,
            'entity_id'         => $registration->id,
            'template_id'       => $template?->id,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at'      => now(),
        ]);
    }

    public function resolveTemplate(TrainingRegistration $registration): ?CertificateTemplate
    {
        $program = $registration->program;
        if (! $program) {
            return null;
        }

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

        $venue = $registration->program?->venue
            ?? $presentSessions->first()?->venue
            ?? '';

        $defaults = [
            'recipient_name'  => $registration->teacher?->name ?? '',
            'program_title'   => $registration->program?->title ?? '',
            'sahodaya_name'   => strtoupper($sahodaya->name),
            'conducted_on'    => $conductedOn,
            'designation'     => $registration->teacher?->designation ?? '',
            'school_name'     => $registration->school?->name ?? '',
            'venue'           => $venue,
            'days_attended'   => (string) $daysAttended,
            'total_days'      => (string) $totalDays,
            'certificate_date'=> now()->format('j F Y'),
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

    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>} */
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

        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name'           => $s['name'] ?? '',
                'designation'    => $s['designation'] ?? '',
                'signature_url'  => ! empty($s['signature_path'])
                    ? TenantStorage::logoUrl($sahodaya, $s['signature_path'])
                    : null,
            ])->values()->all();

        return compact('template', 'fieldValues', 'logoUrl', 'sealUrl', 'signatories');
    }

    /** Demo certificate context for client previews (no real registration). */
    /** @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, certificate: object} */
    public function sampleRenderContext(TrainingProgram $program, Tenant $sahodaya): array
    {
        $template = CertificateTemplate::where('tenant_id', $sahodaya->id)
            ->where('event_type', 'training')
            ->where('certificate_type', 'participation')
            ->where('is_active', true)
            ->latest()
            ->first();

        $conductedOn = $program->start_date?->format('j F Y') ?? '11 July 2026';
        if ($program->start_date && $program->end_date && ! $program->start_date->isSameDay($program->end_date)) {
            $conductedOn = $program->start_date->format('j F Y').' – '.$program->end_date->format('j F Y');
        }

        $fieldValues = [
            'recipient_name'  => 'Mr./Ms. Sample Teacher',
            'designation'     => 'PGT Mathematics',
            'school_name'     => 'Sample Model School',
            'program_title'   => $program->title,
            'sahodaya_name'   => strtoupper($sahodaya->name),
            'conducted_on'    => $conductedOn,
            'venue'           => $program->venue ?? 'St. Alphonsa Public School, Oorakam',
            'days_attended'   => '1',
            'total_days'      => (string) max(1, $program->dayCount() ?: 1),
            'certificate_date'=> now()->format('j F Y'),
        ];

        $logoUrl = $template?->logo_path
            ? TenantStorage::logoUrl($sahodaya, $template->logo_path)
            : TenantBranding::logoUrl($sahodaya);

        $sealUrl = $template?->seal_path
            ? TenantStorage::logoUrl($sahodaya, $template->seal_path)
            : null;

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

        return compact('template', 'fieldValues', 'logoUrl', 'sealUrl', 'signatories', 'certificate');
    }

    public function presentDaysCount(TrainingRegistration $registration): int
    {
        return $this->presentSessions($registration)->count();
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
            ->where('status', 'present')
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
