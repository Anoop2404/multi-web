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
            // Render in safe, memory-bounded chunks of 20 sheets (e.g. 200 cards).
            // Because each chunk is an exact multiple of $perPage (10 cards),
            // NO die cut slots are ever wasted between chunks!
            // Then stitch the chunk PDFs together using FPDI into a single master PDF.
            $sheetsPerChunk = 20;
            $cardsPerChunk = $perPage * $sheetsPerChunk;
            $cardChunks = array_chunk($cards, $cardsPerChunk);
            $totalChunks = count($cardChunks);

            $pdfChunksBytes = [];

            foreach ($cardChunks as $chunkIdx => $chunkCards) {
                $chunkNumber = $chunkIdx + 1;
                $pct = (int) round(($chunkIdx / $totalChunks) * 100);

                $this->updateStatus($tenant, $settingKey, [
                    'status'           => 'rendering',
                    'progress_percent' => $pct,
                    'progress_text'    => "Rendering batch {$chunkNumber} of {$totalChunks} ({$pct}%)...",
                    'processed_chunks' => $chunkIdx,
                    'total_chunks'     => $totalChunks,
                ]);

                $viewData = [
                    'cards'          => $chunkCards,
                    'sections'       => null, // Null triggers continuous packing (zero empty padding slots)
                    'clusterName'    => $tenant->name,
                    'clusterLogoSrc' => TenantBranding::logoEmbedSrc($tenant),
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

                $viewName = $customTemplate ? 'fest.id-cards.custom-sheet' : 'fest.id-cards.sheet';
                $html = view($viewName, $viewData)->render();

                $chunkPdf = PdfGenerator::render(
                    $html,
                    isLandscape: true,
                    pageWidthMm: $customTemplate?->page_width_mm,
                    pageHeightMm: $customTemplate?->page_height_mm,
                    timeoutMs: 180000, // 3 minutes per 20-sheet chunk is more than enough
                );

                if (empty($chunkPdf)) {
                    throw new \RuntimeException("PDF generation returned empty content for batch {$chunkNumber}.");
                }

                $pdfChunksBytes[] = $chunkPdf;
                unset($html, $chunkCards, $viewData);
            }

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
            TenantStorage::disk('s3')->put($s3Path, $finalPdfBytes, 'public');

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
