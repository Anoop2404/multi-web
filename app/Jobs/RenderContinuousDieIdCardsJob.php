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

            $viewData = [
                'cards'          => $cards,
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

            $pdfBytes = PdfGenerator::render(
                $html,
                isLandscape: true,
                pageWidthMm: $customTemplate?->page_width_mm,
                pageHeightMm: $customTemplate?->page_height_mm,
                timeoutMs: 600000, // 10 minutes max for Chromium render
            );

            if (empty($pdfBytes)) {
                throw new \RuntimeException('PDF generation returned empty content.');
            }

            $s3Path = "sahodaya/{$tenant->id}/events/{$event->id}/id-cards/die/full-continuous-run.pdf";
            TenantStorage::disk('s3')->put($s3Path, $pdfBytes, 'public');

            $sizeBytes = strlen($pdfBytes);
            $sizeFormatted = round($sizeBytes / (1024 * 1024), 2).' MB';

            $this->updateStatus($tenant, $settingKey, [
                'status'              => 'completed',
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
