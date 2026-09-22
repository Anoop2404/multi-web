<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use App\Support\TenantStorage;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestIdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestIdCardController extends SahodayaAdminController
{
    use BuildsFestIdCardResponses;
    use ResolvesRegionAwareReportEvent;

    public function index(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $targetEvent->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')->with('phase:id,source_phase_id')]);
        $targetEvent->setRelation('items', \App\Services\Events\FestHeadItemNavigationService::filterToOwnPhase($targetEvent->items, $targetEvent));

        $itemCounts = $service->itemParticipantCounts($targetEvent);
        $registrationCounts = $service->itemRegistrationCounts($targetEvent);
        // Resolve from the original (un-cloned) $event, not $targetEvent — regionAwareTargetEvent()
        // may return a clone with parent_event_id nulled out, which would make ->rootEvent()
        // resolve to itself instead of walking to the real root where class_group_scheme lives.
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $ageGroupLabels = config('fest_item_taxonomy.age_group', []);

        return $this->inertia('Sahodaya/Events/IdCards/Index', $this->withEventActivity($event, FestPageActivity::ID_CARDS, [
            'event'  => $targetEvent->only('id', 'title', 'status', 'event_type'),
            'items'  => $targetEvent->items->map(fn (FestEventItem $item) => [
                'id'                  => $item->id,
                'title'               => $item->title,
                'participant_type'    => $item->participant_type,
                'count'               => $itemCounts[$item->id] ?? 0,
                'registration_count'  => $registrationCounts[$item->id] ?? 0,
                'category_label'      => $this->itemCategoryLabel($item, $classGroupLabels, $ageGroupLabels),
            ]),
            'heads'  => $service->headOptions($targetEvent),
            'meta'   => $service->indexMeta($targetEvent),
            'schools'=> $service->schoolOptions($targetEvent),
            'childEvents' => $this->scopedChildEventOptions($event),
        ]));
    }

    public function cardsJson(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);

        if ($data['audience'] === 'student') {
            $filters['scope'] = $filters['scope'] ?? 'event';
        }

        return response()->json([
            'cards' => $service->cards($targetEvent, $data['audience'], $filters),
        ]);
    }

    public function preview(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);
        $service->requireStudentItem($data['audience'], $filters);
        $cards = $service->cards($targetEvent, $data['audience'], $filters);
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, $filters['item_id'] ?? null, $data['audience']);

        return view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            $data['audience'],
            true,
            null,
            $customTemplate,
        ));
    }

    public function pdf(Request $request, string $tenantId, string $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        // See BoardResultVerificationController::downloadPdf() — implicit route-model
        // binding was found to unreliably deliver the resolved model to PDF/file-download
        // controller methods in production. Resolving manually avoids that failure.
        $event = FestEvent::findOrFail($event);
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        $service->requireStudentItem($data['audience'], $filters);
        $cards = $service->cards($targetEvent, $data['audience'], $filters);
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, $filters['item_id'] ?? null, $data['audience']);

        $audit->festEvent($targetEvent, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'ID cards PDF generated', [
            'audience' => $data['audience'],
            'count'    => count($cards),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
            'scope'    => $filters['scope'] ?? 'item',
        ]);

        $slug = str($targetEvent->title)->slug('-');
        $scopeSuffix = match ($filters['scope'] ?? 'item') {
            'event' => 'event-pass',
            'head'  => 'head-pass',
            default => $data['audience'],
        };

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            $data['audience'],
            false,
            null,
            $customTemplate,
            true,
        ))->render();

        return \App\Support\PdfGenerator::download(
            $html,
            "{$slug}-{$scopeSuffix}-id-cards.pdf",
            isLandscape: true,
            pageWidthMm: $customTemplate?->page_width_mm,
            pageHeightMm: $customTemplate?->page_height_mm,
            requireBrowserRenderer: true,
        );
    }

    public function pdfAllItems(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        // Renders every item's cards in one PDF — for a large event (thousands of
        // students across many items) this is a multiple of what the single-item pdf()
        // above generates, yet it was the only id-card export with no override at all
        // (still bound by php.ini defaults). See docs/SCALE_AND_PAGINATION_PLAN.md §9-new.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);

        $audience = $request->input('audience', 'student');
        abort_unless($audience === 'student', 422, 'Bulk item PDF is available for student cards only.');

        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        unset($filters['item_id'], $filters['head_id'], $filters['scope']);
        $sections = $service->cardsGroupedByItem($targetEvent, $filters);
        abort_if($sections === [], 422, 'No approved participants found for any item.');

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, null, 'student');

        $audit->festEvent($targetEvent, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'All-item ID cards PDF generated', [
            'audience' => 'student',
            'count'    => $totalCards,
            'items'    => count($sections),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
        ]);

        $slug = str($targetEvent->title)->slug('-');

        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();
        
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            true,
        ))->render();

        return \App\Support\PdfGenerator::download(
            $html,
            "{$slug}-all-items-id-cards.pdf",
            isLandscape: true,
            pageWidthMm: $customTemplate?->page_width_mm,
            pageHeightMm: $customTemplate?->page_height_mm,
            requireBrowserRenderer: true,
        );
    }

    public function pdfAllHeads(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        // Same reasoning as pdfAllItems() above — bulk across every item head, same
        // under-provisioning gap. See docs/SCALE_AND_PAGINATION_PLAN.md §9-new.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);

        $audience = $request->input('audience', 'student');
        abort_unless($audience === 'student', 422, 'Bulk head PDF is available for student cards only.');

        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        unset($filters['item_id'], $filters['head_id'], $filters['scope']);
        $sections = collect($service->cardsGroupedByHead($targetEvent, $filters))
            ->map(fn ($section) => [
                'item_title' => $section['head_title'],
                'cards'      => $section['cards'],
            ])
            ->values()
            ->all();

        abort_if($sections === [], 422, 'No approved participants found for any item head.');

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, null, 'student');

        $audit->festEvent($targetEvent, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'All-head ID cards PDF generated', [
            'audience' => 'student',
            'count'    => $totalCards,
            'heads'    => count($sections),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
        ]);

        $slug = str($targetEvent->title)->slug('-');

        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            true,
        ))->render();

        return \App\Support\PdfGenerator::download(
            $html,
            "{$slug}-all-heads-id-cards.pdf",
            isLandscape: true,
            pageWidthMm: $customTemplate?->page_width_mm,
            pageHeightMm: $customTemplate?->page_height_mm,
            requireBrowserRenderer: true,
        );
    }

    public function pdfAllSchools(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);

        $audience = $request->input('audience', 'student');
        abort_unless($audience === 'student', 422, 'Bulk school PDF is available for student cards only.');

        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        unset($filters['head_id']);

        $sections = $service->cardsGroupedBySchool($targetEvent, $filters);
        abort_if($sections === [], 422, 'No approved participants found for any school.');

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, null, 'student');

        $audit->festEvent($targetEvent, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'School-wise bulk ID cards PDF generated', [
            'audience' => 'student',
            'count'    => $totalCards,
            'schools'  => count($sections),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
        ]);

        $slug = str($targetEvent->title)->slug('-');
        $cards = collect($sections)->flatMap(fn ($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            'student',
            false,
            $sections,
            $customTemplate,
            true,
        ))->render();

        return \App\Support\PdfGenerator::download(
            $html,
            "{$slug}-school-wise-id-cards.pdf",
            isLandscape: true,
            pageWidthMm: $customTemplate?->page_width_mm,
            pageHeightMm: $customTemplate?->page_height_mm,
            requireBrowserRenderer: true,
        );
    }

    public function dieGenerator(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, null, 'student');

        $filters = $this->idCardFilters($request);
        $filters['scope'] = 'event';
        $filters['include_data_uris'] = false;

        $gridLayout = $customTemplate?->gridLayout();
        $perPage = $gridLayout ? ($gridLayout['cols'] * $gridLayout['rows']) : ($customTemplate?->cards_per_page ?: 4);

        $schoolList = $service->schoolParticipantSummaries($targetEvent, $filters, $perPage);

        $totalParticipants = 0;
        $totalEstimatedPages = 0;

        foreach ($schoolList as $sc) {
            $totalParticipants += $sc['participant_count'];
            $totalEstimatedPages += $sc['page_count'];
        }

        // Build volumes: group schools into safe batches (~15-20 sheets or ~150-200 students per volume)
        // to ensure Chromium PDF generation completes in 10-15s and never hits Nginx 60s/120s gateway timeouts.
        $volumes = [];
        $currentVolumeSchools = [];
        $currentVolumePages = 0;
        $currentVolumeStudents = 0;
        $volumeIndex = 1;

        foreach ($schoolList as $sc) {
            if ($currentVolumePages > 0 && (
                ($currentVolumePages + $sc['page_count']) > 20
                || ($currentVolumeStudents + $sc['participant_count']) > 200
            )) {
                $volumes[] = [
                    'volume'         => $volumeIndex++,
                    'school_count'   => count($currentVolumeSchools),
                    'student_count'  => $currentVolumeStudents,
                    'page_count'     => $currentVolumePages,
                    'school_from'    => $currentVolumeSchools[0]['school_name'],
                    'school_to'      => end($currentVolumeSchools)['school_name'],
                    'school_ids'     => array_column($currentVolumeSchools, 'school_id'),
                ];
                $currentVolumeSchools = [];
                $currentVolumePages = 0;
                $currentVolumeStudents = 0;
            }

            $currentVolumeSchools[] = $sc;
            $currentVolumePages += $sc['page_count'];
            $currentVolumeStudents += $sc['participant_count'];
        }

        if (! empty($currentVolumeSchools)) {
            $volumes[] = [
                'volume'         => $volumeIndex,
                'school_count'   => count($currentVolumeSchools),
                'student_count'  => $currentVolumeStudents,
                'page_count'     => $currentVolumePages,
                'school_from'    => $currentVolumeSchools[0]['school_name'],
                'school_to'      => end($currentVolumeSchools)['school_name'],
                'school_ids'     => array_column($currentVolumeSchools, 'school_id'),
            ];
        }

        // Sample preview cards are loaded asynchronously by the Vue client on mount
        $firstSchool = $schoolList[0] ?? null;
        $sampleCards = [];

        return $this->inertia('Sahodaya/Events/IdCards/DieGenerator', $this->withEventActivity($event, FestPageActivity::ID_CARDS, [
            'event'               => $targetEvent->only('id', 'title', 'status', 'event_type'),
            'sahodaya'            => $this->sahodaya->only('id', 'name', 'logo_url'),
            'childEvents'         => $this->scopedChildEventOptions($event),
            'schools'             => $schoolList,
            'volumes'             => $volumes,
            'totalParticipants'   => $totalParticipants,
            'totalSchools'        => count($schoolList),
            'totalEstimatedPages' => $totalEstimatedPages,
            'perPage'             => $perPage,
            'activeTemplate'      => $customTemplate ? [
                'id'             => $customTemplate->id,
                'name'           => $customTemplate->title ?? 'Custom Template',
                'card_width_mm'  => $customTemplate->card_width_mm,
                'card_height_mm' => $customTemplate->card_height_mm,
                'page_width_mm'  => $customTemplate->page_width_mm,
                'page_height_mm' => $customTemplate->page_height_mm,
                'grid_layout'    => $gridLayout,
                'fields'         => $customTemplate->fields(),
                'background_url' => $customTemplate->background_path
                    ? TenantStorage::logoUrl($this->sahodaya, $customTemplate->background_path)
                    : null,
            ] : null,
            'previewCards'              => $sampleCards,
            'previewSchoolName'         => $firstSchool['school_name'] ?? null,
            'continuousRenderState'     => $this->formatContinuousRenderState(
                \App\Models\TenantSetting::where('tenant_id', $this->sahodaya->id)
                    ->where('key', "fest_die_render_event_{$targetEvent->id}")
                    ->value('value'),
                $targetEvent
            ),
            'continuousEstimatedSheets' => (int) ceil($totalParticipants / $perPage),
        ]));
    }

    private function formatContinuousRenderState(?array $state, FestEvent $event): ?array
    {
        if (! $state) {
            return null;
        }

        if (($state['status'] ?? null) === 'completed' && ! empty($state['file_path'])) {
            $path = ltrim($state['file_path'], '/');
            $slug = str($event->title)->slug('-');
            $filename = "{$slug}-continuous-master-id-cards.pdf";

            $existsOnS3 = \App\Support\TenantStorage::isS3Configured() && \Illuminate\Support\Facades\Storage::disk('s3')->exists($path);
            $localDisk = \App\Support\TenantStorage::findLocalDisk($path);

            if (! $existsOnS3 && $localDisk && \App\Support\TenantStorage::isS3Configured()) {
                \App\Support\TenantStorage::migrateToS3($path);
                $existsOnS3 = \Illuminate\Support\Facades\Storage::disk('s3')->exists($path);
            }

            if (! $existsOnS3 && ! $localDisk) {
                // File does not physically exist on any disk
                $state['status'] = 'idle';
                $state['error'] = 'Previous master PDF file is no longer on storage. Please generate it again.';
            } elseif ($existsOnS3) {
                try {
                    $state['s3_preview_url'] = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addHours(6), [
                        'ResponseContentDisposition' => 'inline; filename="' . $filename . '"',
                    ]);
                    $state['s3_download_url'] = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addHours(6), [
                        'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed generating presigned S3 URLs: '.$e->getMessage());
                }
            }
        }

        return $state;
    }

    public function dispatchContinuousRender(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $settingKey = "fest_die_render_event_{$targetEvent->id}";

        \App\Models\TenantSetting::updateOrCreate(
            ['tenant_id' => $this->sahodaya->id, 'key' => $settingKey],
            ['value' => [
                'status'    => 'queued',
                'queued_at' => now()->toIso8601String(),
                'error'     => null,
            ]]
        );

        \App\Jobs\RenderContinuousDieIdCardsJob::dispatch($this->sahodaya->id, $targetEvent->id);

        return response()->json([
            'success' => true,
            'status'  => 'queued',
            'message' => 'Continuous master PDF render dispatched to queue.',
        ]);
    }

    public function continuousRenderStatus(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $settingKey = "fest_die_render_event_{$targetEvent->id}";
        $state = \App\Models\TenantSetting::where('tenant_id', $this->sahodaya->id)
            ->where('key', $settingKey)
            ->value('value');

        return response()->json([
            'state' => $this->formatContinuousRenderState($state, $targetEvent) ?: ['status' => 'idle'],
        ]);
    }

    public function downloadContinuousPdf(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);
        $inline = $request->boolean('preview');
        $settingKey = "fest_die_render_event_{$targetEvent->id}";
        $state = \App\Models\TenantSetting::where('tenant_id', $this->sahodaya->id)
            ->where('key', $settingKey)
            ->value('value');

        $relativePath = ltrim($state['file_path'] ?? "sahodaya/{$this->sahodaya->id}/events/{$targetEvent->id}/id-cards/die/full-continuous-run.pdf", '/');
        $slug = str($targetEvent->title)->slug('-');
        $filename = "{$slug}-continuous-master-id-cards.pdf";

        // Try S3 first if configured — redirecting directly to AWS S3 delivers the fastest download with zero server memory overhead
        if (\App\Support\TenantStorage::isS3Configured()) {
            if (! \Illuminate\Support\Facades\Storage::disk('s3')->exists($relativePath)) {
                \App\Support\TenantStorage::migrateToS3($relativePath);
            }

            try {
                if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($relativePath)) {
                    $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"';
                    $s3Url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                        $relativePath,
                        now()->addHours(2),
                        ['ResponseContentDisposition' => $disposition]
                    );

                    return redirect()->away($s3Url);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed generating S3 temporaryUrl for continuous die PDF: '.$e->getMessage());
            }
        }

        // Try across candidate disks
        $candidateDisks = array_values(array_unique(array_filter([
            \App\Support\TenantStorage::uploadDisk(),
            's3',
            \App\Support\TenantStorage::SHARED_DISK,
            'local',
            'public',
        ])));

        foreach ($candidateDisks as $diskName) {
            try {
                $storage = \Illuminate\Support\Facades\Storage::disk($diskName);
                if ($storage->exists($relativePath)) {
                    return response()->stream(function () use ($storage, $relativePath) {
                        $stream = $storage->readStream($relativePath);
                        if (is_resource($stream)) {
                            fpassthru($stream);
                            fclose($stream);
                        } else {
                            echo $storage->get($relativePath);
                        }
                    }, 200, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
                    ]);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $localPath = storage_path('app/shared/' . $relativePath);
        if (file_exists($localPath)) {
            return response()->file($localPath, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
            ]);
        }

        \Illuminate\Support\Facades\Log::warning("Continuous Die PDF not found on any disk: {$relativePath}", [
            'tenant'     => $this->sahodaya->id,
            'event'      => $targetEvent->id,
            'state'      => $state,
            'triedDisks' => $candidateDisks,
        ]);

        $dieGeneratorUrl = "/sahodaya-admin/{$this->sahodaya->id}/events/{$targetEvent->id}/id-cards/die";

        return response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Master PDF Not Ready</title>' .
            '<meta name="viewport" content="width=device-width, initial-scale=1">' .
            '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}' .
            '.card{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:36px;max-width:500px;text-align:center;box-shadow:0 15px 35px rgba(0,0,0,0.5);}' .
            'h1{font-size:20px;font-weight:700;margin-bottom:12px;color:#f59e0b;}' .
            'p{color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:24px;}' .
            'a{display:inline-block;background:#10b981;color:#022c22;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;transition:background 0.2s;}' .
            'a:hover{background:#059669;color:#ffffff;}</style></head><body>' .
            '<div class="card">' .
            '<h1>Master PDF Needs Generation</h1>' .
            '<p>The master continuous PDF has not been generated or the previous render was not saved to storage. Please click the button below to open the Die Generator and click <strong>"Generate Master PDF"</strong>.</p>' .
            '<a href="' . $dieGeneratorUrl . '">Go to Die Generator</a>' .
            '</div></body></html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }

    public function pdfDie(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $targetEvent = $this->regionAwareTargetEvent($request, $event);

        $filters = $this->idCardFilters($request);
        $filters['scope'] = 'event';
        $filters['include_data_uris'] = true;
        unset($filters['head_id']);

        if ($request->filled('school_id')) {
            $filters['school_id'] = $request->input('school_id');
        }

        if ($request->filled('school_ids')) {
            $filters['school_ids'] = (array) $request->input('school_ids');
        }

        $allSections = $service->cardsGroupedBySchool($targetEvent, $filters);
        abort_if($allSections === [], 422, 'No approved participants found for this selection.');

        $sections = $allSections;

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($targetEvent, null, 'student');
        $inlinePreview = $request->boolean('preview');

        $audit->festEvent($targetEvent, FestPageActivity::ID_CARDS, $inlinePreview ? 'fest.id_cards.die_previewed' : 'fest.id_cards.die_generated', $inlinePreview ? 'Die ID cards PDF previewed' : 'Die ID cards PDF generated', [
            'count'     => $totalCards,
            'schools'   => count($sections),
            'volume'    => $request->input('volume'),
            'school_id' => $request->input('school_id'),
        ]);

        $slug = str($targetEvent->title)->slug('-');
        $scopeSuffix = $request->filled('school_id')
            ? ('school-' . ($sections[0]['school_code'] ?? 'die'))
            : ($request->filled('volume') ? ('volume-' . $request->input('volume')) : 'die-all');

        $cards = collect($sections)->flatMap(fn ($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            'student',
            false,
            $sections,
            $customTemplate,
            true,
        ))->render();

        return \App\Support\PdfGenerator::download(
            $html,
            "{$slug}-{$scopeSuffix}-id-cards.pdf",
            inline: $inlinePreview,
            isLandscape: true,
            pageWidthMm: $customTemplate?->page_width_mm,
            pageHeightMm: $customTemplate?->page_height_mm,
            requireBrowserRenderer: true,
        );
    }

    /**
     * Human-readable class/age-bracket or arts-genre label for an item, for display
     * next to the item's title in pickers. Sports events use age_group; everything
     * else uses class_group, falling back to the arts category. Null when nothing
     * more specific than the generic 'open'/'general' buckets applies.
     *
     * @param  array<string, string>  $classGroupLabels
     * @param  array<string, string>  $ageGroupLabels
     */
    private function itemCategoryLabel(FestEventItem $item, array $classGroupLabels, array $ageGroupLabels): ?string
    {
        if ($item->age_group && $item->age_group !== 'open') {
            return $ageGroupLabels[$item->age_group] ?? strtoupper($item->age_group);
        }

        if ($item->class_group && $item->class_group !== 'open') {
            return \App\Support\FestClassGroupScheme::resolveItemLabel($classGroupLabels, $item->class_group);
        }

        if ($item->category && $item->category !== 'general') {
            return config("fest_item_taxonomy.arts_category.{$item->category}")
                ?? ucwords(str_replace(['_', '-'], ' ', $item->category));
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'audience' => 'required|in:student,volunteer,staff',
        ]);
    }
}
