<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestTaxonomyRegistry;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestItemHeadOpsController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $navService = app(FestHeadItemNavigationService::class);
        $nav = $navService->navigationForEvent($event);

        $headId = $this->resolveHeadQueryParam($request->query('head_id'));
        $itemId = $request->integer('item_id') ?: null;
        $selectedItem = null;
        $itemConfig = null;
        $selectedHeadRecord = null;

        if ($itemId) {
            $selectedItem = $navService->findItemInGroups($nav['headItemGroups'], $itemId);
            abort_unless($selectedItem, 404);

            $item = FestEventItem::query()
                ->where('event_id', $event->id)
                ->where('id', $itemId)
                ->first(['id', 'title', 'head_id', 'fee_amount', 'is_enabled', 'owner_level',
                    'reg_start', 'reg_end', 'competition_start', 'competition_end', 'competition_time']);

            if ($item) {
                $itemConfig = [
                    'id'                => $item->id,
                    'title'             => $item->title,
                    'head_id'           => $item->head_id,
                    'fee_amount'        => $item->fee_amount,
                    'is_enabled'        => $item->is_enabled,
                    'reg_start'         => $item->reg_start?->format('Y-m-d'),
                    'reg_end'           => $item->reg_end?->format('Y-m-d'),
                    'competition_start' => $item->competition_start?->format('Y-m-d'),
                    'competition_end'   => $item->competition_end?->format('Y-m-d'),
                    'competition_time'  => $item->competition_time ? substr((string) $item->competition_time, 0, 5) : null,
                    'can_remove'        => ! $item->isStateCatalog(),
                ];
            }
        }

        if ($headId !== null && $headId !== 0) {
            $head = FestItemHead::query()
                ->where('event_id', $event->id)
                ->where('id', $headId)
                ->first(['id', 'name', 'catalog_key', 'is_team_heading', 'sport_discipline',
                    'reg_start', 'reg_end', 'competition_start', 'competition_end',
                    'schedule_mode', 'competition_time',
                    'default_item_fee', 'extra_item_fee']);

            if ($head) {
                $selectedHeadRecord = [
                    'id'                => $head->id,
                    'name'              => $head->name,
                    'is_team_heading'   => $head->is_team_heading,
                    'sport_discipline'  => $head->sport_discipline,
                    'catalog_key'       => $head->catalog_key,
                    'reg_start'         => $head->reg_start?->format('Y-m-d'),
                    'reg_end'           => $head->reg_end?->format('Y-m-d'),
                    'competition_start' => $head->competition_start?->format('Y-m-d'),
                    'competition_end'   => $head->competition_end?->format('Y-m-d'),
                    'schedule_mode'     => $head->schedule_mode ?? 'different_days',
                    'competition_time'  => $head->competitionTimeShort(),
                    'default_item_fee'  => $head->default_item_fee,
                    'extra_item_fee'    => $head->extra_item_fee,
                    'can_remove'        => $head->catalog_key === null,
                ];
            }
        }

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        $registry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);

        return $this->inertia('Sahodaya/Events/ItemHeadOps', $this->withEventActivity($event, FestPageActivity::COMPETITION, array_merge($nav, [
            'event'              => $event->only('id', 'title', 'status', 'event_type', 'results_published'),
            'selectedHeadId'     => $selectedHeadId,
            'selectedItemId'     => $itemId,
            'selectedItem'       => $selectedItem,
            'selectedHeadRecord' => $selectedHeadRecord,
            'itemConfig'         => $itemConfig,
            'disciplines'        => $registry->labels('sport_discipline'),
            'taxonomyMastersUrl' => "/sahodaya-admin/{$this->sahodaya->id}/taxonomy-masters?dimension=sport_discipline",
            'catalogUrl'         => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/items",
            'showHeadFees'       => $event->event_type === 'sports',
        ])));
    }
}
