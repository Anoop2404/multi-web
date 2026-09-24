<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StatePrizeCategory;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StatePrizeCategoryService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Prize categories — named groups of items that crown an individual, school or Sahodaya champion. */
class StatePrizeCategoryController extends Controller
{
    public function index(Request $request, StateFestEvent $event, StatePrizeCategoryService $prizes)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/PrizeCategories', $this->shell($request, $event) + [
            'categories' => $prizes->categories($event)->map(fn (StatePrizeCategory $c) => [
                'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'description' => $c->description,
                'awards' => $c->awards, 'award_labels' => $c->awardLabels(),
                'is_overall' => $c->is_overall, 'is_active' => $c->is_active,
                'honour_count' => $c->honour_count, 'sort_order' => $c->sort_order,
                'item_ids' => $c->items->pluck('item_id'),
                'item_count' => $c->is_overall ? null : $c->items->count(),
            ]),
            'standings' => $prizes->allStandings($event),
            'awardTypes' => StatePrizeCategory::AWARDS,
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('item_code')->get(['id', 'item_code', 'title', 'category', 'class_group']),
            'actionUrls' => [
                'save' => "/admin/state/fest/{$event->id}/prizes",
                'destroy' => "/admin/state/fest/{$event->id}/prizes",
                'assign' => "/admin/state/fest/{$event->id}/prizes",
            ],
        ]);
    }

    public function save(Request $request, StateFestEvent $event, StatePrizeCategoryService $prizes)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'id' => 'nullable|uuid',
            'code' => 'nullable|string|max:40',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'awards' => 'required|array|min:1',
            'awards.*' => 'in:individual,school,sahodaya',
            'is_overall' => 'boolean',
            'honour_count' => 'nullable|integer|min:1|max:50',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $prizes->save($event, $data, $data['id'] ?? null);

        return back()->with('success', 'Prize category saved.');
    }

    public function assign(Request $request, StateFestEvent $event, string $category, StatePrizeCategoryService $prizes)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_ids' => 'present|array',
            'item_ids.*' => 'uuid',
        ]);

        $count = $prizes->assignItems($event, $category, $data['item_ids']);

        return back()->with('success', "{$count} item(s) now count towards this category.");
    }

    public function destroy(Request $request, StateFestEvent $event, string $category, StatePrizeCategoryService $prizes)
    {
        StateScope::assertOwns($event->state_id);

        $prizes->delete($event, $category);

        return back()->with('success', 'Prize category removed.');
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, StateFestEvent $event): array
    {
        return [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district', 'origin']),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
        ];
    }
}
