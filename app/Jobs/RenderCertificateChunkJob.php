<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestIdCardQrService;
use App\Support\PdfGenerator;
use App\Support\TenancyDatabase;
use App\Support\TenantDomainSync;
use App\Support\TenantStorage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;

/**
 * Renders and caches one ~30-certificate slice of a CertificateBatch run (see
 * FestCertificateController::generateAndRenderBatch(), which chunks and dispatches these
 * via Bus::batch()). Each certificate gets both a with-background and a plain (no-
 * background) PDF, persisted via TenantStorage — closing the gap where fest certificates
 * were re-rendered from scratch on every single request
 * (FestCertificateController::downloadZip()/printAll() previously did exactly that).
 */
class RenderCertificateChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

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

    // How many certificates to render between progress-counter flushes — the original
    // design only called CertificateBatch::recordChunkResult() once, at the very end of
    // the whole ~150-certificate chunk. Each certificate does two external Chromium
    // render calls (renderOne(), below), so a slow render service could keep a chunk's
    // processed_count sitting at 0 for well past a queue connection's retry_after before
    // ever writing anything — indistinguishable, from the batch row's own perspective,
    // from a chunk that's actually dead. That's the exact failure BuildCertificateZipChunkJob's
    // docblock describes hitting in production for a sibling pipeline (a single job that
    // only reported progress at the very end); this closes the same gap here.
    private const PROGRESS_FLUSH_EVERY = 5;

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

        $consecutiveConnectionFailures = 0;

        $processedSinceFlush = 0;
        $succeededSinceFlush = 0;
        $failedSinceFlush = 0;
        $failedItemsSinceFlush = [];

        // A few certificates at a time so their PDFs (two each) render concurrently through
        // PdfGenerator::renderMany() — one converter round-trip after another was the bulk
        // of a render run's wall time. Sized so the in-flight request count stays within
        // services.pdf_converter.concurrency.
        $perSlice = max(1, intdiv((int) config('services.pdf_converter.concurrency', 4), 2));

        foreach ($certificates->chunk($perSlice) as $slice) {
            $prepared = [];
            $documents = [];
            $errors = [];

            foreach ($slice as $certificate) {
                try {
                    $prepared[$certificate->id] = $this->prepare($certificate, $service, $qrService, $payloads, $templateCache, $participantsCache, $assetCache);
                    $documents[$certificate->id.':bg'] = $prepared[$certificate->id]['bg'];
                    $documents[$certificate->id.':plain'] = $prepared[$certificate->id]['plain'];
                } catch (\Throwable $e) {
                    $errors[$certificate->id] = $e;
                }
            }

            $pdfs = $documents ? PdfGenerator::renderMany($documents) : [];

            foreach ($slice as $certificate) {
                $processedSinceFlush++;

                try {
                    if (isset($errors[$certificate->id])) {
                        throw $errors[$certificate->id];
                    }

                    $this->persist($certificate, $service, $prepared[$certificate->id], $pdfs[$certificate->id.':bg'], $pdfs[$certificate->id.':plain']);
                    $succeededSinceFlush++;
                    $consecutiveConnectionFailures = 0;
                } catch (ConnectionException $e) {
                    $consecutiveConnectionFailures++;
                    $failedSinceFlush++;
                    $failedItemsSinceFlush[] = $this->failureEntry($certificate, $payloads, $e);

                    if ($consecutiveConnectionFailures >= self::MAX_CONSECUTIVE_CONNECTION_FAILURES) {
                        // The render service itself looks down, not this one certificate —
                        // flush progress for what actually ran, then let tries/backoff retry
                        // the remainder as a fresh chunk attempt once it recovers, rather than
                        // mass-recording everything still unattempted as individually failed.
                        $this->flushProgress($batch, $processedSinceFlush, $succeededSinceFlush, $failedSinceFlush, $failedItemsSinceFlush);

                        throw $e;
                    }

                    continue;
                } catch (\Throwable $e) {
                    $failedSinceFlush++;
                    $failedItemsSinceFlush[] = $this->failureEntry($certificate, $payloads, $e);

                    continue;
                }

                if ($processedSinceFlush >= self::PROGRESS_FLUSH_EVERY) {
                    $this->flushProgress($batch, $processedSinceFlush, $succeededSinceFlush, $failedSinceFlush, $failedItemsSinceFlush);
                }
            }
        }

        $this->flushProgress($batch, $processedSinceFlush, $succeededSinceFlush, $failedSinceFlush, $failedItemsSinceFlush);
    }

    /**
     * Atomically bumps the batch's counters by whatever's accumulated since the last
     * flush and resets those accumulators — called periodically (every
     * PROGRESS_FLUSH_EVERY certificates) rather than once at the end, and takes its
     * counters by reference so callers don't need to duplicate the reset logic.
     */
    private function flushProgress(CertificateBatch $batch, int &$processed, int &$succeeded, int &$failed, array &$failedItems): void
    {
        if ($processed === 0) {
            return;
        }

        $batch->recordChunkResult($processed, $succeeded, $failed);
        if ($failedItems) {
            $batch->appendFailedItems($failedItems);
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $failedItems = [];
    }

    /**
     * Everything up to the converter call: the render context plus both variants' HTML
     * and page geometry, as PdfGenerator::renderMany() documents.
     *
     * @return array{context: array, bg: array, plain: array}
     */
    private function prepare(
        Certificate $certificate,
        FestCertificateService $service,
        FestIdCardQrService $qrService,
        Collection $payloads,
        array &$templateCache,
        array &$participantsCache,
        array &$assetCache,
    ): array {
        $context = $service->renderContext(
            $certificate,
            $payloads->get($certificate->id),
            $templateCache,
            $participantsCache,
            embedAssets: true,
            assetCache: $assetCache,
        );
        // route(..., absolute: true) would use config('app.url') here — this job has no
        // HTTP request to derive a host from, since it runs on a queue worker — which
        // bakes the platform's own default domain into every certificate's QR code
        // instead of the issuing Sahodaya's own domain. Build it from the tenant directly.
        $sahodaya = $context['sahodaya'] ?? null;
        $verifyUrl = ($sahodaya ? TenantDomainSync::publicUrl($sahodaya) : null) ?? url('/');
        $context['qr_src'] = $qrService->dataUri($verifyUrl.'/certificates/verify/'.$certificate->verification_uuid);

        $isLandscape = ($context['overlayLayout']['orientation'] ?? 'landscape') !== 'portrait';
        [$pageWidthMm, $pageHeightMm] = FestCertificateService::customPageDimensionsMm($context['overlayLayout'] ?? []);
        $geometry = ['isLandscape' => $isLandscape, 'pageWidthMm' => $pageWidthMm, 'pageHeightMm' => $pageHeightMm];

        return [
            'context' => $context,
            'bg' => ['html' => view('fest.certificate-print', $context)->render()] + $geometry,
            'plain' => ['html' => view('fest.certificate-print', array_merge($context, ['plainMode' => true]))->render()] + $geometry,
        ];
    }

    /** Stores both rendered variants and marks the certificate fresh. */
    private function persist(Certificate $certificate, FestCertificateService $service, array $prepared, string|\Throwable $withBgPdf, string|\Throwable $plainPdf): void
    {
        foreach ([$withBgPdf, $plainPdf] as $result) {
            if ($result instanceof \Throwable) {
                throw $result;
            }
        }

        $context = $prepared['context'];
        $event = $context['event'] ?? null;
        $tenantId = $context['sahodaya']?->id ?? $this->tenantId;

        $directory = 'certificates/'.$tenantId.'/'.($event?->id ?? '0').'/'.$certificate->cert_type;
        $baseName = $certificate->id.'-'.$certificate->verification_uuid;
        $disk = TenantStorage::uploadDisk();

        $withBgPath = $directory.'/'.$baseName.'.pdf';
        TenantStorage::put($withBgPath, $withBgPdf, $disk);

        $plainPath = $directory.'/'.$baseName.'-plain.pdf';
        TenantStorage::put($plainPath, $plainPdf, $disk);

        $certificate->update([
            'file_path' => $withBgPath,
            'plain_file_path' => $plainPath,
            'storage_disk' => $disk,
            'content_hash' => $service->contentHash($context),
            'is_stale' => false,
            'stale_checked_at' => now(),
            'rendered_at' => now(),
        ]);
    }

    /** @return array{certificate_id: int, name: string, reason: string} */
    private function failureEntry(Certificate $certificate, Collection $payloads, \Throwable $e): array
    {
        $payload = $payloads->get($certificate->id) ?? [];
        $name = $payload['student']?->name ?? 'Participant #'.$certificate->entity_id;

        return [
            'certificate_id' => $certificate->id,
            'name' => $name,
            'reason' => mb_substr($e->getMessage(), 0, 500),
        ];
    }
}
