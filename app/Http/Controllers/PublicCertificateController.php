<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\McqCertificate;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TrainingRegistration;
use App\Services\BoardResults\TopperCertificateService;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestIdCardQrService;
use App\Services\Mcq\McqCertificateService;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenancyDatabase;

class PublicCertificateController extends Controller
{
    public function verify(string $uuid)
    {
        if ($found = $this->findMcqCertificate($uuid)) {
            return $this->verifyMcq($found, $uuid);
        }

        if ($found = $this->findTopperCertificate($uuid)) {
            return $this->verifyTopperFound($found);
        }

        $certificate = Certificate::where('verification_uuid', $uuid)->first();

        if (! $certificate) {
            return view('mcq.certificate-verify', [
                'valid' => false,
                'uuid'  => $uuid,
            ]);
        }

        if ($certificate->entity_type === TrainingRegistration::class) {
            $registration = TrainingRegistration::with(['program', 'teacher', 'school'])->findOrFail($certificate->entity_id);
            $sahodaya = Tenant::findOrFail($registration->program->tenant_id);
            $service = app(TrainingCertificateService::class);

            return view('training.certificate-verify', [
                'certificate'  => $certificate,
                'registration' => $registration,
                'sahodaya'     => $sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $sahodaya),
                'daysPresent'  => $service->presentDaysCount($registration),
            ]);
        }

        if ($certificate->entity_type === TopperCertificateService::ENTITY_TYPE) {
            return $this->verifyTopper($certificate);
        }

        $payload = app(FestCertificateService::class)->payloadFor($certificate);
        $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

        return view('fest.certificate-verify', $payload);
    }

    public function print(string $uuid)
    {
        if ($found = $this->findMcqCertificate($uuid)) {
            return $this->printMcq($found);
        }

        if ($found = $this->findTopperCertificate($uuid)) {
            return $this->printTopperFound($found);
        }

        $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

        if ($certificate->entity_type === TrainingRegistration::class) {
            $registration = TrainingRegistration::with(['program', 'teacher'])->findOrFail($certificate->entity_id);
            $sahodaya = Tenant::findOrFail($registration->program->tenant_id);
            $service = app(TrainingCertificateService::class);
            $render = $service->renderContext($registration, $sahodaya);

            return view('training.certificate', array_merge($render, [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $sahodaya),
            ]));
        }

        if ($certificate->entity_type === TopperCertificateService::ENTITY_TYPE) {
            $topper = Topper::findOrFail($certificate->entity_id);
            $school = Tenant::find($topper->tenant_id);
            $sahodaya = $school?->parent_id ? Tenant::find($school->parent_id) : null;
            abort_unless($sahodaya, 404);

            return $this->printTopperFound([
                'uuid' => $certificate->verification_uuid,
                'sahodaya' => $sahodaya,
            ]);
        }

        $payload = app(FestCertificateService::class)->payloadFor($certificate);
        $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

        return view('fest.certificate-print', $payload);
    }

    /** @param  array{uuid: string, sahodaya: Tenant}  $found */
    private function verifyTopperFound(array $found)
    {
        $sahodaya = $found['sahodaya'];
        $uuid = $found['uuid'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya) {
            $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();
            $topper = Topper::with(['boardResult', 'tenant'])->findOrFail($certificate->entity_id);
            $service = app(TopperCertificateService::class);
            $ctx = $service->renderContext($topper, $sahodaya);

            return view('board_results.topper-certificate-verify', [
                'valid' => true,
                'certificate' => $certificate,
                'fieldValues' => $ctx['fieldValues'],
                'sahodaya' => $sahodaya,
                'printUrl' => route('certificates.print', $uuid, absolute: true),
            ]);
        });
    }

    /** @param  array{uuid: string, sahodaya: Tenant}  $found */
    private function printTopperFound(array $found)
    {
        $sahodaya = $found['sahodaya'];
        $uuid = $found['uuid'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya) {
            $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();
            $topper = Topper::with(['boardResult', 'tenant'])->findOrFail($certificate->entity_id);
            $service = app(TopperCertificateService::class);
            $ctx = $service->renderContext($topper, $sahodaya);
            $body = $ctx['template']?->body ?? \App\Models\CertificateTemplate::defaultTopperBody();
            foreach ($ctx['fieldValues'] as $key => $value) {
                $body = str_replace('{'.$key.'}', (string) $value, $body);
            }

            return view('board_results.topper-certificate', array_merge($ctx, [
                'certificate' => $certificate,
                'bodyHtml' => nl2br(e($body)),
            ]));
        });
    }

    private function verifyTopper(Certificate $certificate)
    {
        $topper = Topper::with(['boardResult', 'tenant'])->findOrFail($certificate->entity_id);
        $school = Tenant::find($topper->tenant_id);
        $sahodaya = $school?->parent_id ? Tenant::find($school->parent_id) : null;
        abort_unless($sahodaya, 404);

        return $this->verifyTopperFound([
            'uuid' => $certificate->verification_uuid,
            'sahodaya' => $sahodaya,
        ]);
    }

    /** @return array{uuid: string, sahodaya: Tenant}|null */
    private function findTopperCertificate(string $uuid): ?array
    {
        if (tenancy()->initialized) {
            $exists = Certificate::where('verification_uuid', $uuid)
                ->where('entity_type', TopperCertificateService::ENTITY_TYPE)
                ->exists();
            if ($exists) {
                $tenant = tenancy()->tenant;

                return [
                    'uuid' => $uuid,
                    'sahodaya' => $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant->getTenantKey()),
                ];
            }

            return null;
        }

        foreach (Tenant::query()->sahodayas()->where('is_active', true)->cursor() as $sahodaya) {
            $exists = TenancyDatabase::whenDatabaseReady($sahodaya, function () use ($uuid) {
                return Certificate::where('verification_uuid', $uuid)
                    ->where('entity_type', TopperCertificateService::ENTITY_TYPE)
                    ->exists();
            }, false);

            if ($exists) {
                return ['uuid' => $uuid, 'sahodaya' => $sahodaya];
            }
        }

        return null;
    }

    /** @param  array{uuid: string, sahodaya: Tenant}  $found */
    private function verifyMcq(array $found, string $uuid)
    {
        $sahodaya = $found['sahodaya'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya) {
            $certificate = McqCertificate::where('verification_uuid', $uuid)->firstOrFail();
            $registration = $certificate->registration()->with(['exam', 'student', 'teacher', 'school'])->first();
            $exam = $registration?->exam;

            return view('mcq.certificate-verify', [
                'valid'        => true,
                'uuid'         => $uuid,
                'recipient'    => $registration?->participantName() ?: '—',
                'examTitle'    => $exam?->title ?: '—',
                'examCode'     => $exam?->code,
                'schoolName'   => $registration?->school?->name ?: '—',
                'sahodayaName' => $sahodaya->name,
                'issuedAt'     => $certificate->generated_at?->format('d M Y') ?: '—',
                'printUrl'     => route('certificates.print', $uuid, absolute: true),
            ]);
        });
    }

    /** @param  array{uuid: string, sahodaya: Tenant}  $found */
    private function printMcq(array $found)
    {
        $sahodaya = $found['sahodaya'];
        $uuid = $found['uuid'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya) {
            $certificate = McqCertificate::where('verification_uuid', $uuid)->firstOrFail();
            $registration = $certificate->registration()->with(['exam', 'student', 'teacher', 'school', 'mark'])->firstOrFail();

            return view('mcq.certificate', [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $sahodaya,
                'fields'       => app(McqCertificateService::class)->fieldValues($registration, $sahodaya),
                'design'       => $certificate->design_snapshot_json ?? [],
            ]);
        });
    }

    /** @return array{uuid: string, sahodaya: Tenant}|null */
    private function findMcqCertificate(string $uuid): ?array
    {
        if (tenancy()->initialized) {
            if (McqCertificate::where('verification_uuid', $uuid)->exists()) {
                $tenant = tenancy()->tenant;

                return [
                    'uuid'     => $uuid,
                    'sahodaya' => $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant->getTenantKey()),
                ];
            }

            return null;
        }

        foreach (Tenant::query()->sahodayas()->where('is_active', true)->cursor() as $sahodaya) {
            $exists = TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($uuid) {
                return McqCertificate::where('verification_uuid', $uuid)->exists();
            });

            if ($exists) {
                return ['uuid' => $uuid, 'sahodaya' => $sahodaya];
            }
        }

        return null;
    }
}
