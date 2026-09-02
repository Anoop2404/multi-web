<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Http\Controllers\SahodayaAdmin\Concerns\ResolvesRegionAwareReportEvent;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
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
        $targetEvent->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

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

        $isDomPdf = empty(config('services.pdf_converter.url'));
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $targetEvent,
            $this->sahodaya,
            $cards,
            $data['audience'],
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-{$scopeSuffix}-id-cards.pdf");
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

        $data = $this->validated($request);
        abort_unless($data['audience'] === 'student', 422, 'Bulk item PDF is available for student cards only.');

        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        unset($filters['item_id'], $filters['scope']);
        $sections = $service->cardsGroupedByItem($event, $filters);
        abort_if($sections === [], 422, 'No approved participants found for any item.');

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($event, null, 'student');

        $audit->festEvent($event, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'All-item ID cards PDF generated', [
            'audience' => 'student',
            'count'    => $totalCards,
            'items'    => count($sections),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
        ]);

        $slug = str($event->title)->slug('-');

        $isDomPdf = empty(config('services.pdf_converter.url'));
        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();
        
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $this->sahodaya,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-all-items-id-cards.pdf");
    }

    public function pdfAllHeads(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        // Same reasoning as pdfAllItems() above — bulk across every item head, same
        // under-provisioning gap. See docs/SCALE_AND_PAGINATION_PLAN.md §9-new.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request);
        abort_unless($data['audience'] === 'student', 422, 'Bulk head PDF is available for student cards only.');

        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        unset($filters['item_id'], $filters['head_id'], $filters['scope']);
        $sections = collect($service->cardsGroupedByHead($event, $filters))
            ->map(fn ($section) => [
                'item_title' => $section['head_title'],
                'cards'      => $section['cards'],
            ])
            ->values()
            ->all();

        abort_if($sections === [], 422, 'No approved participants found for any item head.');

        $totalCards = collect($sections)->sum(fn ($section) => count($section['cards']));
        $customTemplate = $this->resolveCustomIdCardTemplate($event, null, 'student');

        $audit->festEvent($event, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'All-head ID cards PDF generated', [
            'audience' => 'student',
            'count'    => $totalCards,
            'heads'    => count($sections),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
        ]);

        $slug = str($event->title)->slug('-');

        $isDomPdf = empty(config('services.pdf_converter.url'));
        $cards = collect($sections)->flatMap(fn($section) => $section['cards'])->values()->all();

        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $this->sahodaya,
            $cards,
            'student',
            false,
            null,
            $customTemplate,
            $isDomPdf,
        ))->render();

        return \App\Support\PdfGenerator::download($html, "{$slug}-all-heads-id-cards.pdf");
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
