<?php

namespace App\Services\Training;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Models\TrainingAttendance;
use App\Models\TrainingSession;
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

        $sessions = $registration->program?->sessions ?? collect();
        if ($sessions->isEmpty()) {
            return;
        }

        $presentCount = TrainingAttendance::where('registration_id', $registration->id)
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('status', 'present')
            ->count();

        if ($presentCount < $sessions->count()) {
            throw ValidationException::withMessages([
                'attendance' => 'All training sessions must be marked present before issuing a certificate.',
            ]);
        }
    }

    public function issue(TrainingRegistration $registration): Certificate
    {
        $this->assertEligible($registration);
        $registration->load(['program', 'teacher']);

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
            ->latest()
            ->first();
    }

    /** @return array<string, string> */
    public function resolveFieldValues(TrainingRegistration $registration, Tenant $sahodaya): array
    {
        $registration->loadMissing(['program', 'teacher']);

        $defaults = [
            'recipient_name' => $registration->teacher?->name ?? '',
            'program_title'  => $registration->program?->title ?? '',
            'sahodaya_name'  => $sahodaya->name,
            'conducted_on'   => $registration->program?->registration_open?->format('d M Y') ?? now()->format('d M Y'),
            'designation'    => $registration->teacher?->designation ?? '',
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
}
