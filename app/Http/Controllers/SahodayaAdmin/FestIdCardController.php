<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsFestIdCardResponses;
use App\Models\FestEvent;
use App\Support\FestPageActivity;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestIdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestIdCardController extends SahodayaAdminController
{
    use BuildsFestIdCardResponses;

    public function index(string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['items' => fn ($q) => $q->where('is_enabled', true)->orderBy('title')]);

        $itemCounts = $service->itemParticipantCounts($event);
        $registrationCounts = $service->itemRegistrationCounts($event);

        return $this->inertia('Sahodaya/Events/IdCards/Index', $this->withEventActivity($event, FestPageActivity::ID_CARDS, [
            'event'  => $event->only('id', 'title', 'status', 'event_type'),
            'items'  => $event->items->map(fn ($item) => [
                'id'                  => $item->id,
                'title'               => $item->title,
                'participant_type'    => $item->participant_type,
                'count'               => $itemCounts[$item->id] ?? 0,
                'registration_count'  => $registrationCounts[$item->id] ?? 0,
            ]),
            'heads'  => $service->headOptions($event),
            'meta'   => $service->indexMeta($event),
            'schools'=> $service->schoolOptions($event),
        ]));
    }

    public function cardsJson(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);

        if ($data['audience'] === 'student') {
            $scope = $filters['scope'] ?? 'event';
            if ($scope === 'item' && empty($filters['item_id'])) {
                $filters['scope'] = 'event';
            }
            if ($scope === 'head' && empty($filters['head_id'])) {
                $filters['scope'] = 'event';
            }
        }

        return response()->json([
            'cards' => $service->cards($event, $data['audience'], $filters),
        ]);
    }

    public function preview(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);
        $service->requireStudentItem($data['audience'], $filters);
        $cards = $service->cards($event, $data['audience'], $filters);
        $customTemplate = $this->resolveCustomIdCardTemplate($event, $filters['item_id'] ?? null, $data['audience']);

        return view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
            $this->sahodaya,
            $cards,
            $data['audience'],
            true,
            null,
            $customTemplate,
        ));
    }

    public function pdf(Request $request, string $tenantId, FestEvent $event, FestIdCardService $service, PlatformAuditLogger $audit)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request);
        $filters = $this->idCardFilters($request);
        $filters['include_data_uris'] = true;
        $service->requireStudentItem($data['audience'], $filters);
        $cards = $service->cards($event, $data['audience'], $filters);
        $customTemplate = $this->resolveCustomIdCardTemplate($event, $filters['item_id'] ?? null, $data['audience']);

        $audit->festEvent($event, FestPageActivity::ID_CARDS, 'fest.id_cards.generated', 'ID cards PDF generated', [
            'audience' => $data['audience'],
            'count'    => count($cards),
            'template' => $customTemplate ? 'custom:'.$customTemplate->id : $request->input('template', 'standard'),
            'scope'    => $filters['scope'] ?? 'item',
        ]);

        $slug = str($event->title)->slug('-');
        $scopeSuffix = match ($filters['scope'] ?? 'item') {
            'event' => 'event-pass',
            'head'  => 'head-pass',
            default => $data['audience'],
        };

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
        $html = view($this->idCardSheetView($request, $customTemplate), $this->idCardViewData(
            $event,
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

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
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

        $isDomPdf = empty(env('PDF_CONVERTER_URL'));
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

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'audience' => 'required|in:student,volunteer,staff',
        ]);
    }
}
