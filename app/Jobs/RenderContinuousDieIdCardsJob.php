<?php

namespace App\Jobs;

use App\Models\FestEvent;
use App\Models\IdCardTemplate;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Events\FestIdCardService;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use App\Support\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenderContinuousDieIdCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes max execution time for queue worker

    public int $tries = 1;

    public function __construct(
        public string $tenantId,
        public int $eventId,
    ) {}

    public function handle(FestIdCardService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $tenant->run(function () use ($tenant, $service) {
            $this->executeRender($tenant, $service);
        });
    }

    private function executeRender(Tenant $tenant, FestIdCardService $service): void
    {
        $settingKey = "fest_die_render_event_{$this->eventId}";

        $this->updateStatus($tenant, $settingKey, [
            'status' => 'rendering',
            'started_at' => now()->toIso8601String(),
            'error' => null,
        ]);

        try {
            @ini_set('memory_limit', '2048M');
            @set_time_limit(0);

            $event = FestEvent::findOrFail($this->eventId);
            $customTemplate = IdCardTemplate::resolveFor($event, null, 'student');

            $filters = [
                'scope' => 'event',
                'include_data_uris' => true,
            ];
            $cards = $service->cards($event, 'student', $filters);

            if (empty($cards)) {
                $this->updateStatus($tenant, $settingKey, [
                    'status' => 'failed',
                    'error' => 'No approved participants found for this event.',
                ]);

                return;
            }

            // Sort sequentially across schools so students flow continuously without gaps
            $cards = collect($cards)->sortBy([
                ['school_name', 'asc'],
                ['name', 'asc'],
            ])->values()->all();

            $gridLayout = $customTemplate?->gridLayout();
            $perPage = $gridLayout ? ($gridLayout['cols'] * $gridLayout['rows']) : ($customTemplate?->cards_per_page ?: 4);
            $totalSheets = (int) ceil(count($cards) / $perPage);

            $backgroundUrl = null;
            if ($customTemplate?->background_path) {
                $backgroundUrl = TenantStorage::backgroundDataUri($tenant, $customTemplate->background_path)
                    ?: (($u = TenantStorage::logoUrl($tenant, $customTemplate->background_path)) && ! str_starts_with($u, '/') ? $u : url($u ?? ''));
            }

            // For ultra-high volume events (up to 4,500+ students / 450 sheets):
            // Render in safe, memory-bounded chunks of 10 sheets (e.g. 100 cards).
            // Because each chunk is an exact multiple of $perPage (10 cards),
            // NO die cut slots are ever wasted between chunks!
            // Then stitch the chunk PDFs together using FPDI into a single master PDF.
            $sheetsPerChunk = 10;
            $cardsPerChunk = $perPage * $sheetsPerChunk;
            $cardChunks = array_chunk($cards, $cardsPerChunk);
            $totalChunks = count($cardChunks);

            $viewName = $customTemplate ? 'fest.id-cards.custom-sheet' : 'fest.id-cards.sheet';
            $clusterLogoSrc = TenantBranding::logoEmbedSrc($tenant);

            $this->updateStatus($tenant, $settingKey, [
                'status'           => 'rendering',
                'progress_percent' => 0,
                'progress_text'    => "Rendering {$totalChunks} batch(es)...",
                'processed_chunks' => 0,
                'total_chunks'     => $totalChunks,
            ]);

            // Chunks go to the converter through PdfGenerator::renderEach(): a rolling
            // window of services.pdf_converter.concurrency requests, the next chunk sent as
            // soon as any in-flight one finishes, instead of one chunk at a time. A chunk's
            // HTML (card photos embedded as data URIs) is only built when a slot frees, so
            // no more than that many are held at once. Chunks complete in any order and are
            // put back in sequence before merging.
            $documents = (function () use ($cardChunks, $tenant, $event, $clusterLogoSrc, $backgroundUrl, $customTemplate, $gridLayout, $viewName) {
                foreach ($cardChunks as $chunkIdx => $chunkCards) {
                    $viewData = [
                        'cards'          => $chunkCards,
                        'sections'       => null, // Null triggers continuous packing (zero empty padding slots)
                        'clusterName'    => $tenant->name,
                        'clusterLogoSrc' => $clusterLogoSrc,
                        'eventTitle'     => $event->title,
                        'audience'       => 'student',
                        'showTitle'      => false,
                        'isPdf'          => true,
                        'backgroundUrl'  => $backgroundUrl,
                        'fields'         => $customTemplate?->fields() ?? [],
                        'cardWidthMm'    => $customTemplate?->card_width_mm ?? 90,
                        'cardHeightMm'   => $customTemplate?->card_height_mm ?? 140,
                        'cardsPerPage'   => $customTemplate?->cards_per_page ?? 4,
                        'pageWidthMm'    => $customTemplate?->page_width_mm,
                        'pageHeightMm'   => $customTemplate?->page_height_mm,
                        'gridLayout'     => $gridLayout,
                    ];

                    yield $chunkIdx => [
                        'html'         => view($viewName, $viewData)->render(),
                        'isLandscape'  => true,
                        'pageWidthMm'  => $customTemplate?->page_width_mm,
                        'pageHeightMm' => $customTemplate?->page_height_mm,
                    ];
                }
            })();

            $pdfChunksBytes = [];

            PdfGenerator::renderEach(
                $documents,
                function ($chunkIdx, $chunkPdf) use (&$pdfChunksBytes, $totalChunks, $tenant, $settingKey) {
                    if ($chunkPdf instanceof \Throwable) {
                        throw $chunkPdf;
                    }
                    if (empty($chunkPdf)) {
                        throw new \RuntimeException('PDF generation returned empty content for batch '.($chunkIdx + 1).'.');
                    }

                    $pdfChunksBytes[$chunkIdx] = $chunkPdf;
                    $done = count($pdfChunksBytes);
                    $pct = (int) round(($done / $totalChunks) * 100);

                    $this->updateStatus($tenant, $settingKey, [
                        'status'           => 'rendering',
                        'progress_percent' => $pct,
                        'progress_text'    => "Rendered batch {$done} of {$totalChunks} ({$pct}%)...",
                        'processed_chunks' => $done,
                        'total_chunks'     => $totalChunks,
                    ]);
                },
                timeoutMs: 180000, // 3 minutes per 10-sheet chunk is more than enough
                requireBrowserRenderer: true,
            );

            ksort($pdfChunksBytes);
            $pdfChunksBytes = array_values($pdfChunksBytes);

            // Merge all chunk PDFs into one single continuous master PDF
            if ($totalChunks === 1) {
                $finalPdfBytes = $pdfChunksBytes[0];
            } else {
                $this->updateStatus($tenant, $settingKey, [
                    'status'        => 'rendering',
                    'progress_text' => 'Merging all PDF batches into single master file...',
                ]);
                $finalPdfBytes = $this->mergePdfChunks($pdfChunksBytes);
            }

            $s3Path = "sahodaya/{$tenant->id}/events/{$event->id}/id-cards/die/full-continuous-run.pdf";

            // 1. Always save to local shared storage first so it is guaranteed present on the server
            try {
                Storage::disk(TenantStorage::SHARED_DISK)->put($s3Path, $finalPdfBytes);
            } catch (\Throwable $e) {
                Log::warning('Failed saving continuous die PDF to shared disk: '.$e->getMessage());
            }

            // 2. Also save to upload disk if different
            $uploadDisk = TenantStorage::uploadDisk();
            if ($uploadDisk !== TenantStorage::SHARED_DISK && $uploadDisk !== 's3') {
                try {
                    Storage::disk($uploadDisk)->put($s3Path, $finalPdfBytes);
                } catch (\Throwable $e) {
                    Log::warning("Failed saving continuous die PDF to {$uploadDisk} disk: ".$e->getMessage());
                }
            }

            // 3. Save directly to AWS S3 without ACLs ('public') for direct presigned streaming
            if (TenantStorage::isS3Configured()) {
                try {
                    // Do NOT pass 'public' ACL as modern S3 buckets disable ACLs by default (BucketOwnerEnforced)
                    Storage::disk('s3')->put($s3Path, $finalPdfBytes);
                } catch (\Throwable $e) {
                    Log::error('Failed saving continuous die PDF to S3: '.$e->getMessage());
                }
            }

            $sizeBytes = strlen($finalPdfBytes);
            $sizeFormatted = round($sizeBytes / (1024 * 1024), 2).' MB';

            $this->updateStatus($tenant, $settingKey, [
                'status'              => 'completed',
                'progress_percent'    => 100,
                'progress_text'       => 'Completed',
                'total_cards'         => count($cards),
                'total_sheets'        => $totalSheets,
                'file_path'           => $s3Path,
                'file_size_bytes'     => $sizeBytes,
                'file_size_formatted' => $sizeFormatted,
                'rendered_at'         => now()->toIso8601String(),
                'rendered_at_human'   => now()->format('d M Y, h:i A'),
                'error'               => null,
            ]);

            Log::info("Continuous Die ID cards rendered successfully for tenant {$tenant->id}, event {$event->id}: {$totalSheets} sheets, {$sizeFormatted}");
        } catch (\Throwable $e) {
            Log::error('Failed to render continuous die ID cards: '.$e->getMessage(), [
                'tenant' => $tenant->id,
                'event'  => $this->eventId,
                'trace'  => $e->getTraceAsString(),
            ]);

            $this->updateStatus($tenant, $settingKey, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Merge multiple raw PDF byte strings into a single continuous PDF string using FPDI.
     */
    private function mergePdfChunks(array $pdfChunksBytes): string
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        $tempFiles = [];

        try {
            foreach ($pdfChunksBytes as $bytes) {
                $tmpPath = tempnam(sys_get_temp_dir(), 'die_chunk_');
                file_put_contents($tmpPath, $bytes);
                $tempFiles[] = $tmpPath;

                $pageCount = $pdf->setSourceFile($tmpPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['orientation'] ?? 'P') === 'L' ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            return $pdf->Output('S');
        } finally {
            foreach ($tempFiles as $tmpPath) {
                @unlink($tmpPath);
            }
        }
    }

    private function updateStatus(Tenant $tenant, string $key, array $data): void
    {
        $existing = TenantSetting::where('tenant_id', $tenant->id)->where('key', $key)->first();
        $merged = array_merge($existing?->value ?? [], $data);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => $key],
            ['value' => $merged]
        );
    }
}
