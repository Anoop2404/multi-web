<?php

namespace App\Jobs;

use App\Models\CertificateBatch;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestCertificateService;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds one event's certificate ZIP in the background — see
 * FestCertificateController::queueZipExport(), which replaced downloadZip()'s
 * synchronous, whole-request build for the "bulk" ZIP triggers (whole event, merit-only,
 * participation-only, per-school, and ad-hoc bulk selection). An event with hundreds to
 * thousands of certificates could exceed the web server/proxy's own request timeout well
 * before PHP's set_time_limit(600) in downloadZip() ever kicked in; this runs on a queue
 * worker instead, with Certificates.vue polling FestCertificateController::batchProgress()
 * — the exact same endpoint RenderCertificateChunkJob already reports through — for
 * status, then downloading via downloadZipResult() once complete.
 *
 * tries=1, deliberately no retry: recordChunkResult() below is a cumulative counter bump,
 * not an idempotent set, so a from-scratch retry after a partial failure would double-
 * count processed/succeeded/failed. Unlike RenderCertificateChunkJob (whose narrow
 * ConnectionException retry only isolates the render service being temporarily down),
 * nothing here is precious to preserve on failure — the source certificates and their
 * already-cached PDFs are untouched, so a failed export just needs the admin to click the
 * ZIP button again, which starts a fresh CertificateBatch row from zero.
 */
class BuildCertificateZipJob implements ShouldQueue
{
    use Queueable;

    // Generous relative to a web request — this runs on a queue worker. Matches
    // RenderCertificateChunkJob's own reasoning for the same figure.
    public int $timeout = 1800;

    public int $tries = 1;

    // How many certificates to add to the ZIP between progress-counter flushes — frequent
    // enough that Certificates.vue's 3s poll sees real movement on a large export, rare
    // enough not to turn a 1,000+ certificate run into 1,000+ UPDATE statements.
    private const PROGRESS_FLUSH_EVERY = 25;

    public function __construct(
        public int $certificateBatchId,
        public string $tenantId,
        public int $eventId,
        public bool $publishedOnly,
        public ?int $itemId,
        public ?int $schoolId,
        public ?string $certType,
        public ?array $certIds,
        public bool $plain,
    ) {}

    public function handle(FestCertificateService $service): void
    {
        $batch = CertificateBatch::find($this->certificateBatchId);
        if (! $batch || $batch->status === CertificateBatch::STATUS_CANCELLED) {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            $batch->update(['status' => CertificateBatch::STATUS_FAILED, 'error' => 'Tenant not found.', 'completed_at' => now()]);

            return;
        }

        TenancyDatabase::withTenantDatabase($tenant, function () use ($service, $batch, $tenant) {
            $this->buildZip($service, $batch, $tenant);
        });
    }

    public function failed(\Throwable $exception): void
    {
        CertificateBatch::find($this->certificateBatchId)?->update([
            'status' => CertificateBatch::STATUS_FAILED,
            'error' => mb_substr($exception->getMessage(), 0, 2000),
            'completed_at' => now(),
        ]);
    }

    private function buildZip(FestCertificateService $service, CertificateBatch $batch, Tenant $tenant): void
    {
        $event = FestEvent::find($this->eventId);
        if (! $event) {
            $batch->update(['status' => CertificateBatch::STATUS_FAILED, 'error' => 'Event not found.', 'completed_at' => now()]);

            return;
        }

        $payloads = $service->exportPayloadsForEvent(
            $event,
            embedAssets: true,
            plain: $this->plain,
            publishedOnly: $this->publishedOnly,
            itemId: $this->itemId,
            schoolId: $this->schoolId,
            certType: $this->certType,
            certIds: $this->certIds,
            sahodaya: $tenant,
        );

        if ($payloads->isEmpty()) {
            $batch->update([
                'status' => CertificateBatch::STATUS_FAILED,
                'error' => $this->publishedOnly ? 'No published winner certificates to download.' : 'No certificates to download.',
                'completed_at' => now(),
            ]);

            return;
        }

        $localPath = storage_path('app/tmp/fest-certs-'.$event->id.'-batch'.$batch->id.'.zip');
        @mkdir(dirname($localPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($localPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $processedSinceFlush = 0;
        $succeededSinceFlush = 0;
        $failedSinceFlush = 0;

        foreach ($payloads as $payload) {
            $certificate = $payload['certificate'];

            try {
                $pdf = $service->cachedOrFreshPdf($certificate, fn () => $payload, $this->plain);
                $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$certificate->verification_uuid.'.pdf';
                $zip->addFromString($name, $pdf);
                $succeededSinceFlush++;
            } catch (\Throwable) {
                $failedSinceFlush++;
            }

            $processedSinceFlush++;

            if ($processedSinceFlush >= self::PROGRESS_FLUSH_EVERY) {
                $batch->recordChunkResult($processedSinceFlush, $succeededSinceFlush, $failedSinceFlush);
                $processedSinceFlush = $succeededSinceFlush = $failedSinceFlush = 0;
            }
        }

        if ($processedSinceFlush > 0) {
            $batch->recordChunkResult($processedSinceFlush, $succeededSinceFlush, $failedSinceFlush);
        }

        $zip->close();

        $disk = TenantStorage::uploadDisk();
        $relativePath = 'certificate-exports/'.$tenant->id.'/'.$event->id.'/'.$batch->id.'.zip';

        $stream = fopen($localPath, 'r');
        Storage::disk($disk)->put($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($localPath);

        $filename = str($event->title)->slug()
            .($this->publishedOnly ? '-published-winners' : ($this->certType ? '-'.$this->certType : '-certificates'))
            .($this->plain ? '-plain' : '').'.zip';

        $batch->refresh();
        $batch->update([
            'status' => $batch->failed_count > 0 ? CertificateBatch::STATUS_COMPLETED_WITH_ERRORS : CertificateBatch::STATUS_COMPLETED,
            'file_path' => $relativePath,
            'storage_disk' => $disk,
            'result_filename' => $filename,
            'completed_at' => now(),
        ]);
    }
}
