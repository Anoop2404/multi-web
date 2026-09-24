<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestPrizeCategory;
use App\Services\Events\FestPrizeCategoryService;
use Illuminate\Http\Request;

/**
 * Prize categories for a Sahodaya fest — named groups of items that crown an individual or a school
 * champion. The Sahodaya counterpart of StateAdmin\Fest\StatePrizeCategoryController.
 */
class FestPrizeCategoryController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestPrizeCategoryService $prizes)
    {
        $this->assertOwns($event);

        return $this->inertia('Sahodaya/Events/PrizeCategories', [
            'event' => $event->only(['id', 'title', 'results_published']),
            'categories' => $prizes->categories($event)->map(fn (FestPrizeCategory $c) => [
                'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'description' => $c->description,
                'awards' => $c->awards, 'award_labels' => $c->awardLabels(),
                'is_overall' => $c->is_overall, 'is_active' => $c->is_active,
                'honour_count' => $c->honour_count, 'sort_order' => $c->sort_order,
                'item_ids' => $c->items->pluck('item_id'),
                'item_count' => $c->is_overall ? null : $c->items->count(),
            ]),
            'standings' => $prizes->allStandings($event),
            'awardTypes' => FestPrizeCategory::AWARDS,
            'items' => FestEventItem::where('event_id', $event->id)
                ->orderBy('item_code')->get(['id', 'item_code', 'title', 'category', 'class_group']),
            'actionUrls' => $this->actionUrls($tenantId, $event),
        ]);
    }

    public function save(Request $request, string $tenantId, FestEvent $event, FestPrizeCategoryService $prizes)
    {
        $this->assertOwns($event);

        $data = $request->validate([
            'id' => 'nullable|integer',
            'code' => 'nullable|string|max:40',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'awards' => 'required|array|min:1',
            'awards.*' => 'in:individual,school',
            'is_overall' => 'boolean',
            'honour_count' => 'nullable|integer|min:1|max:50',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $prizes->save($event, $data, $data['id'] ?? null);

        return back()->with('success', 'Prize category saved.');
    }

    public function assign(Request $request, string $tenantId, FestEvent $event, FestPrizeCategory $category, FestPrizeCategoryService $prizes)
    {
        $this->assertOwns($event);
        abort_unless($category->event_id === $event->id, 404);

        $data = $request->validate(['item_ids' => 'present|array', 'item_ids.*' => 'integer']);

        $count = $prizes->assignItems($event, $category->id, $data['item_ids']);

        return back()->with('success', "{$count} item(s) now count towards this category.");
    }

    public function destroy(Request $request, string $tenantId, FestEvent $event, FestPrizeCategory $category, FestPrizeCategoryService $prizes)
    {
        $this->assertOwns($event);
        abort_unless($category->event_id === $event->id, 404);

        $prizes->delete($event, $category->id);

        return back()->with('success', 'Prize category removed.');
    }

    private function assertOwns(FestEvent $event): void
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
    }

    /** @return array<string, string> */
    private function actionUrls(string $tenantId, FestEvent $event): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/prizes";

        return [
            'index' => $base,
            'save' => $base,
            'assign' => $base,
            'destroy' => $base,
        ];
    }
}
