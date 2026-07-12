<?php

namespace App\Services\BoardResults;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\TenantBranding;
use Illuminate\Support\Str;

/**
 * Topper "Congratulations" certificates (#151).
 * Uses Certificate entity_type = topper + CertificateTemplate event_type = topper.
 */
class TopperCertificateService
{
    public const ENTITY_TYPE = 'topper';

    public const CERT_TYPE = 'congratulations';

    public function issueForBoardResult(\App\Models\BoardResult $boardResult, string $sahodayaId): int
    {
        $boardResult->loadMissing('toppers');
        $issued = 0;

        foreach ($boardResult->toppers as $topper) {
            $this->issue($topper, $sahodayaId);
            $issued++;
        }

        return $issued;
    }

    public function issue(Topper $topper, string $sahodayaId): Certificate
    {
        $existing = Certificate::query()
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', $topper->id)
            ->where('cert_type', self::CERT_TYPE)
            ->first();

        if ($existing) {
            return $existing;
        }

        $template = $this->resolveTemplate($sahodayaId);

        return Certificate::create([
            'entity_type' => self::ENTITY_TYPE,
            'entity_id' => $topper->id,
            'template_id' => $template?->id,
            'cert_type' => self::CERT_TYPE,
            'verification_uuid' => (string) Str::uuid(),
            'generated_at' => now(),
        ]);
    }

    public function resolveTemplate(string $sahodayaId): ?CertificateTemplate
    {
        return CertificateTemplate::query()
            ->where('tenant_id', $sahodayaId)
            ->where('event_type', self::ENTITY_TYPE)
            ->where('certificate_type', self::CERT_TYPE)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * @return array{template: ?CertificateTemplate, fieldValues: array<string, string>, logoUrl: ?string, sealUrl: ?string, signatories: list<array>, topper: Topper, school: ?Tenant, sahodaya: ?Tenant, boardResult: ?\App\Models\BoardResult}
     */
    public function renderContext(Topper $topper, Tenant $sahodaya): array
    {
        $topper->loadMissing(['boardResult', 'tenant', 'subjectMarks']);
        $template = $this->resolveTemplate($sahodaya->id);
        $school = $topper->tenant;
        $boardResult = $topper->boardResult;

        $subjectMarksText = $topper->subjectMarks
            ->sortBy('subject_label')
            ->map(fn ($m) => trim(($m->subject_label ?? '').': '.number_format((float) $m->marks, 1)))
            ->filter()
            ->implode('; ');

        $fieldValues = [
            'recipient_name' => $topper->name,
            'school_name' => $school?->name ?? '—',
            'sahodaya_name' => $sahodaya->name,
            'academic_year' => $boardResult?->academic_year ?? '—',
            'class' => $boardResult ? (string) $boardResult->class : '—',
            'examination_type' => $boardResult?->examination_type ?? '—',
            'percentage' => $topper->percentage !== null ? number_format((float) $topper->percentage, 2).'%' : '—',
            'rank' => $topper->rank !== null ? (string) $topper->rank : '—',
            'stream' => $topper->stream ?? '—',
            'admission_no' => $topper->admission_no ?? '—',
            'roll_no' => $topper->roll_no ?? '—',
            'subject_marks' => $subjectMarksText !== '' ? $subjectMarksText : '—',
        ];

        $branding = TenantBranding::for($sahodaya);
        $signatories = collect($template?->signatories ?? CertificateTemplate::defaultTrainingSignatories())
            ->map(fn ($s) => [
                'name' => $s['name'] ?? '',
                'designation' => $s['designation'] ?? '',
                'signature_path' => $s['signature_path'] ?? null,
            ])
            ->all();

        return [
            'template' => $template,
            'fieldValues' => $fieldValues,
            'logoUrl' => \App\Support\TenantStorage::logoUrl($sahodaya, $template?->logo_path)
                ?: ($branding['logo_url'] ?? null),
            'sealUrl' => \App\Support\TenantStorage::logoUrl($sahodaya, $template?->seal_path),
            'signatories' => $signatories,
            'topper' => $topper,
            'school' => $school,
            'sahodaya' => $sahodaya,
            'boardResult' => $boardResult,
        ];
    }
}
