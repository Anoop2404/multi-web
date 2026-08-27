<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\CertificateIndex;
use App\Models\CertificateTemplate;
use App\Models\FestParticipant;
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
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;

class PublicCertificateController extends Controller
{
    public function verify(string $uuid)
    {
        $owner = $this->resolveCertificateOwner($uuid);

        if (! $owner) {
            return view('mcq.certificate-verify', [
                'valid' => false,
                'uuid' => $uuid,
            ]);
        }

        if ($owner['source'] === CertificateIndex::SOURCE_MCQ) {
            return $this->verifyMcq($owner, $uuid);
        }

        $sahodaya = $owner['sahodaya'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya) {
            $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

            if ($certificate->entity_type === TrainingRegistration::class) {
                $registration = TrainingRegistration::with(['program', 'teacher', 'school'])->findOrFail($certificate->entity_id);
                $service = app(TrainingCertificateService::class);

                return view('training.certificate-verify', [
                    'certificate' => $certificate,
                    'registration' => $registration,
                    'sahodaya' => $sahodaya,
                    'fieldValues' => $service->resolveFieldValues($registration, $sahodaya),
                    'daysPresent' => $service->presentDaysCount($registration),
                ]);
            }

            if ($certificate->entity_type === TopperCertificateService::ENTITY_TYPE) {
                return $this->verifyTopperFound(['uuid' => $certificate->verification_uuid, 'sahodaya' => $sahodaya]);
            }

            $payload = app(FestCertificateService::class)->payloadFor($certificate);
            $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri((TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/verify/'.$certificate->verification_uuid);

            return view('fest.certificate-verify', $payload);
        });
    }

    public function print(string $uuid, Request $request)
    {
        $owner = $this->resolveCertificateOwner($uuid);
        abort_unless($owner, 404);

        if ($owner['source'] === CertificateIndex::SOURCE_MCQ) {
            return $this->printMcq($owner);
        }

        $sahodaya = $owner['sahodaya'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya, $request) {
            $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

            if ($certificate->entity_type === TrainingRegistration::class) {
                $registration = TrainingRegistration::with(['program', 'teacher'])->findOrFail($certificate->entity_id);
                $service = app(TrainingCertificateService::class);
                $render = $service->renderContext($registration, $sahodaya);

                return view('training.certificate', array_merge($render, [
                    'registration' => $registration,
                    'certificate' => $certificate,
                    'sahodaya' => $sahodaya,
                    'fieldValues' => $service->resolveFieldValues($registration, $sahodaya),
                ]));
            }

            if ($certificate->entity_type === TopperCertificateService::ENTITY_TYPE) {
                return $this->printTopperFound(['uuid' => $certificate->verification_uuid, 'sahodaya' => $sahodaya]);
            }

            $payload = app(FestCertificateService::class)->renderContext($certificate);
            $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri((TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/verify/'.$certificate->verification_uuid);
            // ?preview=1 (from the Sahodaya admin's own Certificates page) reuses this same
            // public print view but hides the Print/Save button and auto-fits the page to
            // the viewport — the same isSample behavior the template-preview screens use,
            // just applied to a real generated certificate instead of mock data.
            $payload['isSample'] = $request->boolean('preview');

            return view('fest.certificate-print', $payload);
        });
    }

    /**
     * Serves the real rendered PDF (from RenderCertificateChunkJob's cache, or a fresh
     * render on a miss/stale) — unlike print() above, which always returns the
     * browser-printable HTML page. Same public/throttled/uuid-keyed trust boundary as
     * print(), since that link already sits right next to this one in the admin UI and a
     * tighter boundary here would be an inconsistent, confusing regression rather than a
     * real tightening (the certificate is already reachable via print()'s link).
     */
    public function pdf(string $uuid, Request $request)
    {
        $owner = $this->resolveCertificateOwner($uuid);
        abort_unless($owner && $owner['source'] === CertificateIndex::SOURCE_CERTIFICATE, 404);

        $sahodaya = $owner['sahodaya'];

        return TenancyDatabase::withTenantDatabase($sahodaya, function () use ($uuid, $sahodaya, $request) {
            $certificate = Certificate::where('verification_uuid', $uuid)->firstOrFail();

            // file_path/plain_file_path (what this route serves) are only ever populated for
            // Fest certificates via RenderCertificateChunkJob — training/topper/mcq certs have
            // their own dedicated print/verify flows above and never populate these columns.
            abort_unless($certificate->entity_type === FestParticipant::class, 404);

            $service = app(FestCertificateService::class);
            $plain = $request->boolean('plain');

            $pdf = $service->cachedOrFreshPdf($certificate, function () use ($certificate, $service, $sahodaya) {
                $payload = $service->renderContext($certificate, embedAssets: true);
                $payload['qr_src'] = app(FestIdCardQrService::class)->dataUri((TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/verify/'.$certificate->verification_uuid);

                return $payload;
            }, $plain);

            $studentName = $service->payloadFor($certificate)['student']?->name ?? 'certificate';
            $filename = str($studentName)->slug().'-'.$certificate->verification_uuid.($plain ? '-plain' : '').'.pdf';

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            ]);
        });
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
                'printUrl' => (TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/print/'.$uuid,
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
            $body = $ctx['template']?->body ?? CertificateTemplate::defaultTopperBody();
            foreach ($ctx['fieldValues'] as $key => $value) {
                $body = str_replace('{'.$key.'}', (string) $value, $body);
            }

            return view('board_results.topper-certificate', array_merge($ctx, [
                'certificate' => $certificate,
                'bodyHtml' => nl2br(e($body)),
            ]));
        });
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
                'valid' => true,
                'uuid' => $uuid,
                'recipient' => $registration?->participantName() ?: '—',
                'examTitle' => $exam?->title ?: '—',
                'examCode' => $exam?->code,
                'schoolName' => $registration?->school?->name ?: '—',
                'sahodayaName' => $sahodaya->name,
                'issuedAt' => $certificate->generated_at?->format('d M Y') ?: '—',
                'printUrl' => (TenantDomainSync::publicUrl($sahodaya) ?? url('/')).'/certificates/print/'.$uuid,
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
                'certificate' => $certificate,
                'sahodaya' => $sahodaya,
                'fields' => app(McqCertificateService::class)->fieldValues($registration, $sahodaya),
                'design' => $certificate->design_snapshot_json ?? [],
            ]);
        });
    }

    /**
     * Finds which Sahodaya's database a certificate uuid belongs to, without depending on
     * the request's domain (these routes are public and uuid-keyed — the host carries no
     * tenant information, and per the bug this fixes, is not even reliably the right
     * tenant's own domain). Central-index lookup first (O(1)); falls back to scanning
     * every active Sahodaya's database for pre-index certificates, self-healing the index
     * on a hit so that cost is paid at most once per certificate, ever.
     *
     * @return array{uuid: string, sahodaya: Tenant, source: string}|null
     */
    private function resolveCertificateOwner(string $uuid): ?array
    {
        if (tenancy()->initialized) {
            $tenant = tenancy()->tenant;
            $sahodaya = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant->getTenantKey());

            return match (true) {
                McqCertificate::where('verification_uuid', $uuid)->exists() => ['uuid' => $uuid, 'sahodaya' => $sahodaya, 'source' => CertificateIndex::SOURCE_MCQ],
                Certificate::where('verification_uuid', $uuid)->exists() => ['uuid' => $uuid, 'sahodaya' => $sahodaya, 'source' => CertificateIndex::SOURCE_CERTIFICATE],
                default => null,
            };
        }

        if ($indexed = CertificateIndex::where('verification_uuid', $uuid)->first()) {
            if ($sahodaya = Tenant::find($indexed->tenant_id)) {
                return ['uuid' => $uuid, 'sahodaya' => $sahodaya, 'source' => $indexed->source_table];
            }
            // Tenant deleted since indexing — fall through to the scan, which will also
            // fail and produce the normal "not found" response.
        }

        foreach (Tenant::query()->sahodayas()->where('is_active', true)->cursor() as $sahodaya) {
            $source = TenancyDatabase::whenDatabaseReady($sahodaya, function () use ($uuid) {
                return match (true) {
                    McqCertificate::where('verification_uuid', $uuid)->exists() => CertificateIndex::SOURCE_MCQ,
                    Certificate::where('verification_uuid', $uuid)->exists() => CertificateIndex::SOURCE_CERTIFICATE,
                    default => null,
                };
            }, null);

            if ($source) {
                CertificateIndex::recordFor($uuid, $sahodaya->id, $source);

                return ['uuid' => $uuid, 'sahodaya' => $sahodaya, 'source' => $source];
            }
        }

        return null;
    }
}
