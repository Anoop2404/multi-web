<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateEligibilityService;
use App\Services\State\Fest\StateGradingService;
use App\Services\State\StateGradePointService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Grade Master, Grade & Rank Points, and Categories & Eligibility for a State event. */
class StateGradingController extends Controller
{
    public function grades(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['item_id' => 'nullable|uuid']);
        $itemId = $filters['item_id'] ?? null;

        return Inertia::render('State/Fest/Grades', $this->shell($request, $event) + [
            'filters' => $filters,
            'bands' => $grading->bands($event, $itemId),
            // Said explicitly so an operator can tell a scale they set from one inherited from the
            // event or from the manual.
            'isItemOverride' => $itemId !== null
                && \App\Models\State\StateGradeBand::where('state_event_id', $event->id)
                    ->where('item_id', $itemId)->exists(),
            'source' => app(StateGradePointService::class)->sourceFor($event),
            'standards' => $this->standards($grading),
            'items' => $this->items($event),
            'actionUrls' => [
                'save' => "/admin/state/fest/{$event->id}/grades",
                'applyStandard' => "/admin/state/fest/{$event->id}/grades/apply-standard",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/grades",
        ]);
    }

    public function saveGrades(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_id' => 'nullable|uuid',
            'bands' => 'required|array|min:1',
            'bands.*.grade' => 'required|string|max:20',
            'bands.*.min_score' => 'required|numeric|min:0|max:9999',
            'bands.*.max_score' => 'required|numeric|min:0|max:9999',
        ]);

        $grading->saveBands($event, $data['bands'], $data['item_id'] ?? null);

        return back()->with('success', 'Grade scale saved. Marks already aggregated keep the grade they were given — re-aggregate the item to apply this.');
    }

    public function points(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/Points', $this->shell($request, $event) + [
            'rules' => $grading->rules($event),
            'source' => app(StateGradePointService::class)->sourceFor($event),
            'standards' => $this->standards($grading),
            'grades' => $grading->bands($event)->pluck('grade')->values(),
            'actionUrls' => [
                'save' => "/admin/state/fest/{$event->id}/points",
                'applyStandard' => "/admin/state/fest/{$event->id}/points/apply-standard",
            ],
        ]);
    }

    public function savePoints(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'rules' => 'present|array',
            'rules.*.grade' => 'nullable|string|max:20',
            'rules.*.position' => 'nullable|integer|min:1|max:50',
            'rules.*.is_group' => 'boolean',
            'rules.*.points' => 'required|integer|min:0|max:1000',
        ]);

        $grading->saveRules($event, $data['rules']);

        return back()->with('success', 'Point rules saved. Recompute an item\'s result to apply them.');
    }

    /** Both tabs load the same standard table; which one they came from decides where they go back to. */
    public function applyStandard(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'table' => 'required|string|in:'.StateGradingService::DEFAULT_GRADING.','.StateGradingService::CONFED_SCORING,
        ]);

        $result = $grading->applyManualStandard($event, $data['table']);

        return back()->with('success', "Loaded the standard table — {$result['bands']} grade band(s) and {$result['rules']} point rule(s). "
            .'Recompute each item to apply them to results already calculated.');
    }

    // ── Categories and eligibility ─────────────────────────────────────────────────────────

    public function eligibility(Request $request, StateFestEvent $event, StateGradingService $grading, StateEligibilityService $eligibility)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate([
            'sahodaya_id' => 'nullable|uuid',
            'item_id' => 'nullable|uuid',
        ]);

        return Inertia::render('State/Fest/Eligibility', $this->shell($request, $event) + [
            'filters' => $filters,
            'categories' => $grading->categories($event)->map(fn ($c) => [
                'id' => $c->id, 'code' => $c->code, 'label' => $c->label,
                'min_class' => $c->min_class, 'max_class' => $c->max_class,
                'is_open' => $c->is_open, 'range' => $c->range(),
                'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                    ->where('class_group', $c->code)->count(),
            ]),
            'unknownCategories' => $grading->itemsWithUnknownCategory($event),
            'summary' => $eligibility->summary($event),
            'audit' => $eligibility->audit($event, $filters),
            'items' => $this->items($event),
            'schemes' => config('fest_class_group_schemes.options', []),
            'actionUrls' => [
                'seed' => "/admin/state/fest/{$event->id}/eligibility/seed",
                'save' => "/admin/state/fest/{$event->id}/eligibility/categories",
                'destroy' => "/admin/state/fest/{$event->id}/eligibility/categories",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/eligibility",
        ]);
    }

    public function seedCategories(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate(['scheme' => 'required|string|max:40']);
        $count = $grading->seedCategories($event, $data['scheme']);

        return back()->with('success', "{$count} categor(ies) loaded. Existing ones were updated, not duplicated.");
    }

    public function saveCategory(Request $request, StateFestEvent $event, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'id' => 'nullable|uuid',
            'code' => 'required|string|max:40',
            'label' => 'required|string|max:255',
            'min_class' => 'nullable|integer|min:1|max:12',
            'max_class' => 'nullable|integer|min:1|max:12',
            'is_open' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $grading->saveCategory($event, $data, $data['id'] ?? null);

        return back()->with('success', 'Category saved.');
    }

    public function destroyCategory(Request $request, StateFestEvent $event, string $category, StateGradingService $grading)
    {
        StateScope::assertOwns($event->state_id);

        $grading->deleteCategory($event, $category);

        return back()->with('success', 'Category removed.');
    }

    /** @return list<array{key: string, label: string, description: string}> */
    private function standards(StateGradingService $grading): array
    {
        return [
            [
                'key' => StateGradingService::DEFAULT_GRADING,
                'label' => 'Kalotsav standard (with No Grade band)',
                'description' => 'The table the Sahodaya side loads one-click. A, B, C plus a No Grade band, so a mark below 50 still resolves to something.',
            ],
            [
                'key' => StateGradingService::CONFED_SCORING,
                'label' => 'Confederation State Kalotsavam manual',
                'description' => 'Grade points plus place points, summed, exactly as the manual states. Group items are double throughout.',
            ],
        ];
    }

    private function items(StateFestEvent $event)
    {
        return FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->orderBy('item_code')->get(['id', 'item_code', 'title', 'class_group']);
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
