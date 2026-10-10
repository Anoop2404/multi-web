<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestTrophy;
use App\Services\Events\FestTrophyService;
use App\Support\FestClassGroupScheme;
use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FestTrophyController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $root = $event->rootEvent();
        $usesPhases = $root->usesPhasedRegionalBilling();
        $scope = $request->input('scope', 'phase');
        if (! $usesPhases && $scope === 'cumulative') {
            $scope = 'phase';
        }

        $trophies = $trophyService->trophiesForEvent($event);
        $resolvedRows = $trophyService->resolveWinners($event, $trophies, cumulative: $scope === 'cumulative');

        $parent = $event->parent_event_id ? FestEvent::find($event->parent_event_id) : null;
        $childEvents = FestEvent::where('parent_event_id', $event->id)->get(['id', 'title', 'event_type']);

        $categoryLabels = FestClassGroupScheme::labels(null, $root);
        $items = FestEventItem::where('event_id', $event->id)
            ->orderBy('item_code')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'category', 'class_group', 'participant_type']);

        $stats = [
            'total_trophies' => $trophies->count(),
            'resolved_winners' => $resolvedRows->where('has_winner', true)->count(),
            'rolling_trophies' => $trophies->where('is_rolling', true)->count(),
        ];

        return $this->inertia('Sahodaya/Events/Trophies', $this->withEventActivity($event, FestPageActivity::RESULTS, [
            'event' => $event,
            'scope' => $scope,
            'usesPhases' => $usesPhases,
            'trophyRows' => $resolvedRows->values(),
            'stats' => $stats,
            'hierarchy' => [
                'is_child' => $event->parent_event_id !== null,
                'parent_id' => $parent?->id,
                'parent_title' => $parent?->title,
                'child_count' => $childEvents->count(),
                'children' => $childEvents,
            ],
            'items' => $items,
            'categoryOptions' => collect($categoryLabels)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'trophyTypes' => [
                FestTrophy::TYPE_OVERALL => 'Overall Points',
                FestTrophy::TYPE_CATEGORY => 'Category-wise Points',
                FestTrophy::TYPE_ITEM => 'Single Item Winner',
                FestTrophy::TYPE_ITEM_GROUP => 'Item Group / Cluster',
                FestTrophy::TYPE_INDIVIDUAL_CHAMPIONSHIP => 'Individual Championship',
            ],
        ]));
    }

    public function save(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'id' => 'nullable|integer',
            'trophy_no' => 'required|integer|min:1|max:999',
            'title' => 'required|string|max:255',
            'trophy_type' => 'required|string|in:overall,category,item,item_group,individual_championship',
            'position' => 'required|integer|min:1|max:50',
            'award_type' => 'required|string|in:school,individual',
            'category_key' => 'nullable|string|max:50',
            'item_id' => 'nullable|integer',
            'item_name_pattern' => 'nullable|string|max:100',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
            'item_group_name' => 'nullable|string|max:100',
            'gender' => 'nullable|string|in:male,female',
            'notes' => 'nullable|string|max:255',
            'is_rolling' => 'boolean',
            'donor_name' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $trophyService->saveTrophy($event, $data, $data['id'] ?? null);

        return back()->with('success', 'Trophy saved successfully.');
    }

    public function destroy(Request $request, string $tenantId, FestEvent $event, FestTrophy $trophy, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($trophy->event_id === $event->id, 404);

        $trophyService->deleteTrophy($event, $trophy->id);

        return back()->with('success', 'Trophy removed.');
    }

    public function seedPreset(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $count = $trophyService->seedKochiMetroPreset($event);

        return back()->with('success', "Standard 75-trophy template seeded successfully ({$count} trophies created).");
    }

    public function copyFromParent(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->parent_event_id, 422, 'This event does not have a parent event.');

        $count = $trophyService->copyFromParent($event);

        return back()->with('success', "Inherited {$count} trophies from parent event.");
    }

    public function pushToChildren(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $count = $trophyService->pushToChildEvents($event);

        return back()->with('success', "Propagated {$count} trophies across all child events.");
    }

    public function exportPdf(Request $request, string $tenantId, FestEvent $event, FestTrophyService $trophyService): Response
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $scope = $request->input('scope', 'phase');
        $trophies = $trophyService->trophiesForEvent($event);
        $resolvedRows = $trophyService->resolveWinners($event, $trophies, cumulative: $scope === 'cumulative');

        $slug = \Illuminate\Support\Str::slug($event->title);
        $filename = "{$slug}-trophy-distribution-list.pdf";

        return PdfGenerator::fromView('fest.reports.trophy-distribution', [
            'event' => $event,
            'sahodaya' => $this->sahodaya,
            'rows' => $resolvedRows,
            'cumulative' => $scope === 'cumulative',
        ], $filename, inline: $request->boolean('inline'), isLandscape: false);
    }
}
