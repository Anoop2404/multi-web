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
 * Appends one ~40-certificate slice to a CertificateBatch's ZIP export — see
 * FestCertificateController::queueZipExport(), which chains these via Bus::chain()
 * (strictly sequential, never concurrent like Bus::batch() — every chunk appends to the
 * SAME on-disk ZipArchive file, which two processes can't safely write at once).
 *
 * This replaces an earlier single-job version of this feature that covered a whole
 * export in one job — it failed in production on a 906-certificate event with "has been
 * attempted too many times", even though nothing had actually crashed: the batch's
 * processed_count showed real progress (325/906) at the moment it died. Root cause: any
 * queue connection's retry_after (90s by default here — config/queue.php) elapsing while
 * the job was still genuinely running makes the queue driver hand the same job to a
 * second worker, which burns through $tries. Chunking bounds each individual job's
 * runtime well under any reasonable retry_after, the same way RenderCertificateChunkJob
 * already does for rendering — 40 rather than that job's 150, since this one may need to
 * fresh-render (not just zip a cached file) if an admin exports before running Step 2.
 */
class BuildCertificateZipChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $certificateBatchId,
        public string $tenantId,
        public int $eventId,
        public array $certificateIds,
        public bool $isFinalChunk,
        public bool $plain,
        public string $resultFilename,
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
            $this->processChunk($service, $batch, $tenant);
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

    private function processChunk(FestCertificateService $service, CertificateBatch $batch, Tenant $tenant): void
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
            certIds: $this->certificateIds,
            sahodaya: $tenant,
        );

        $localPath = storage_path('app/tmp/fest-certs-batch'.$batch->id.'.zip');
        @mkdir(dirname($localPath), 0755, true);

        $zip = new \ZipArchive;
        // The first chunk to run creates the file; every later one (guaranteed to run
        // only after the previous one fully finished, via Bus::chain()) reopens and
        // appends to what earlier chunks already wrote.
        $flags = file_exists($localPath) ? 0 : (\ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->open($localPath, $flags);

        $succeeded = 0;
        $failed = 0;

        foreach ($payloads as $payload) {
            $certificate = $payload['certificate'];

            try {
                $pdf = $service->cachedOrFreshPdf($certificate, fn () => $payload, $this->plain);
                $name = str($payload['student']?->name ?? 'participant')->slug().'-'.$certificate->verification_uuid.'.pdf';
                $zip->addFromString($name, $pdf);
                $succeeded++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $zip->close();

        $batch->recordChunkResult(count($this->certificateIds), $succeeded, $failed);

        if (! $this->isFinalChunk) {
            return;
        }

        $disk = TenantStorage::uploadDisk();
        $relativePath = 'certificate-exports/'.$tenant->id.'/'.$event->id.'/'.$batch->id.'.zip';

        $stream = fopen($localPath, 'r');
        Storage::disk($disk)->put($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($localPath);

        $batch->refresh();
        $batch->update([
            'status' => $batch->failed_count > 0 ? CertificateBatch::STATUS_COMPLETED_WITH_ERRORS : CertificateBatch::STATUS_COMPLETED,
            'file_path' => $relativePath,
            'storage_disk' => $disk,
            'result_filename' => $this->resultFilename,
            'completed_at' => now(),
        ]);
    }
}
