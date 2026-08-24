<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestIdCardQrService;
use App\Support\PdfGenerator;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

/**
 * Renders and caches one ~150-certificate slice of a CertificateBatch run (see
 * FestCertificateController::generateAndRenderBatch(), which chunks and dispatches these
 * via Bus::batch()). Each certificate gets both a with-background and a plain (no-
 * background) PDF, persisted via TenantStorage — closing the gap where fest certificates
 * were re-rendered from scratch on every single request
 * (FestCertificateController::downloadZip()/printAll() previously did exactly that).
 */
class RenderCertificateChunkJob implements ShouldQueue
{
    use Queueable, Batchable;

    // Generous relative to a web request's timeout — this runs on a queue worker, not
    // behind a browser. Matches the one existing precedent for a job of comparable scope,
    // MigrateLegacyUploadsJob::$timeout = 3600. The queue connection's retry_after must
    // stay above this or Laravel will consider a still-running chunk "lost" and re-
    // dispatch it mid-render.
    public int $timeout = 1800;

    public int $tries = 3;

    // Consecutive connection failures to the Chromium renderer within one chunk before
    // giving up on the rest of it and letting the job's own retry/backoff take over —
    // distinguishes "the render service is down" (abort chunk, retry later) from "this
    // one certificate's own data is bad" (isolate, keep going). See renderChunk().
    private const MAX_CONSECUTIVE_CONNECTION_FAILURES = 3;

    public function __construct(
        public int $certificateBatchId,
        public array $certificateIds,
        public string $tenantId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(FestCertificateService $service, FestIdCardQrService $qrService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        TenancyDatabase::withTenantDatabase($tenant, function () use ($service, $qrService) {
            $this->renderChunk($service, $qrService);
        });
    }

    private function renderChunk(FestCertificateService $service, FestIdCardQrService $qrService): void
    {
        $batch = CertificateBatch::find($this->certificateBatchId);
        if (! $batch || $batch->status === CertificateBatch::STATUS_CANCELLED) {
            return;
        }

        $certificates = Certificate::whereIn('id', $this->certificateIds)->get();
        $payloads = $service->payloadsFor($certificates);

        $templateCache = [];
        $participantsCache = [];
        $assetCache = [];

        $succeeded = 0;
        $failed = 0;
        $failedItems = [];
        $consecutiveConnectionFailures = 0;
        $processedBeforeAbort = 0;

        foreach ($certificates as $certificate) {
            $processedBeforeAbort++;

            try {
                $this->renderOne($certificate, $service, $qrService, $payloads, $templateCache, $participantsCache, $assetCache);
                $succeeded++;
                $consecutiveConnectionFailures = 0;
            } catch (ConnectionException $e) {
                $consecutiveConnectionFailures++;

                if ($consecutiveConnectionFailures >= self::MAX_CONSECUTIVE_CONNECTION_FAILURES) {
                    // The render service itself looks down, not this one certificate —
                    // record progress for what actually ran, then let tries/backoff retry
                    // the remainder as a fresh chunk attempt once it recovers, rather than
                    // mass-recording everything still unattempted as individually failed.
                    $batch->recordChunkResult($processedBeforeAbort, $succeeded, $failed);
                    if ($failedItems) {
                        $batch->appendFailedItems($failedItems);
                    }

                    throw $e;
                }

                $failed++;
                $failedItems[] = $this->failureEntry($certificate, $payloads, $e);
            } catch (\Throwable $e) {
                $failed++;
                $failedItems[] = $this->failureEntry($certificate, $payloads, $e);
            }
        }

        $batch->recordChunkResult(count($certificates), $succeeded, $failed);
        if ($failedItems) {
            $batch->appendFailedItems($failedItems);
        }
    }

    private function renderOne(
        Certificate $certificate,
        FestCertificateService $service,
        FestIdCardQrService $qrService,
        \Illuminate\Support\Collection $payloads,
        array &$templateCache,
        array &$participantsCache,
        array &$assetCache,
    ): void {
        $context = $service->renderContext(
            $certificate,
            $payloads->get($certificate->id),
            $templateCache,
            $participantsCache,
            embedAssets: true,
            assetCache: $assetCache,
        );
        $context['qr_src'] = $qrService->dataUri(route('certificates.verify', $certificate->verification_uuid, absolute: true));

        $event = $context['event'] ?? null;
        $tenantId = $context['sahodaya']?->id ?? $this->tenantId;
        $isLandscape = ($context['overlayLayout']['orientation'] ?? 'landscape') !== 'portrait';

        $directory = 'certificates/'.$tenantId.'/'.($event?->id ?? '0').'/'.$certificate->cert_type;
        $baseName = $certificate->id.'-'.$certificate->verification_uuid;
        $disk = TenantStorage::uploadDisk();

        $withBgHtml = view('fest.certificate-print', $context)->render();
        $withBgPdf = PdfGenerator::render($withBgHtml, $isLandscape);
        $withBgPath = $directory.'/'.$baseName.'.pdf';
        TenantStorage::put($withBgPath, $withBgPdf, $disk);

        $plainHtml = view('fest.certificate-print', array_merge($context, ['plainMode' => true]))->render();
        $plainPdf = PdfGenerator::render($plainHtml, $isLandscape);
        $plainPath = $directory.'/'.$baseName.'-plain.pdf';
        TenantStorage::put($plainPath, $plainPdf, $disk);

        $certificate->update([
            'file_path'        => $withBgPath,
            'plain_file_path'  => $plainPath,
            'storage_disk'     => $disk,
            'content_hash'     => $service->contentHash($context),
            'is_stale'         => false,
            'stale_checked_at' => now(),
            'rendered_at'      => now(),
        ]);
    }

    /** @return array{certificate_id: int, name: string, reason: string} */
    private function failureEntry(Certificate $certificate, \Illuminate\Support\Collection $payloads, \Throwable $e): array
    {
        $payload = $payloads->get($certificate->id) ?? [];
        $name = $payload['student']?->name ?? 'Participant #'.$certificate->entity_id;

        return [
            'certificate_id' => $certificate->id,
            'name'           => $name,
            'reason'         => mb_substr($e->getMessage(), 0, 500),
        ];
    }
}
