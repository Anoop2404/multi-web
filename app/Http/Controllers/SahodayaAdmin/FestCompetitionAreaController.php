<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FestCompetitionAreaController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $areas = FestCompetitionArea::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('items')
            ->get();

        return $this->inertia('Sahodaya/Events/Areas/Index', $this->withFestNavContext([
            'event' => $event->only('id', 'title', 'event_type', 'status'),
            'areas' => $areas,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request, $event);

        FestCompetitionArea::create([
            ...$data,
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'slug' => $this->uniqueSlug($event, $data['name'], $data['slug'] ?? null),
            'is_active' => true,
        ]);

        return back()->with('success', 'Area added.');
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestCompetitionArea $area)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($area->event_id !== $event->id, 404);

        $data = $this->validated($request, $event, $area);
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($event, $data['name'], null, $area->id);
        }

        $area->update($data);

        return back()->with('success', 'Area updated.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestCompetitionArea $area)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($area->event_id !== $event->id, 404);

        if ($area->items()->exists()) {
            $area->update(['is_active' => false]);

            return back()->with('success', 'Area deactivated (items still linked).');
        }

        $area->delete();

        return back()->with('success', 'Area removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, FestEvent $event, ?FestCompetitionArea $area = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'slug' => [
                'nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('fest_competition_areas', 'slug')
                    ->where('event_id', $event->id)
                    ->ignore($area?->id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('fest_competition_areas', 'id')->where('event_id', $event->id),
            ],
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
            'reg_start' => 'nullable|date',
            'reg_end' => 'nullable|date|after_or_equal:reg_start',
            'competition_start' => 'nullable|date',
            'competition_end' => 'nullable|date|after_or_equal:competition_start',
            'competition_time' => 'nullable|string|max:8',
            'school_registration_fee' => 'nullable|numeric|min:0',
            'student_registration_fee' => 'nullable|numeric|min:0',
            'team_registration_fee' => 'nullable|numeric|min:0',
            'default_item_fee' => 'nullable|numeric|min:0',
            'extra_item_fee' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0',
            'included_teams' => 'nullable|integer|min:0',
            'verification_policy' => 'nullable|in:all_students,verified_only',
            'approval_policy' => 'nullable|in:auto,manual',
            'max_participants' => 'nullable|integer|min:0',
            'max_teams' => 'nullable|integer|min:0',
            'venue' => 'nullable|string|max:255',
        ]);
    }

    private function uniqueSlug(FestEvent $event, string $name, ?string $slug, ?int $ignoreId = null): string
    {
        $base = $slug ?: (Str::slug($name) ?: 'area');
        $candidate = $base;
        $n = 2;
        while (
            FestCompetitionArea::where('event_id', $event->id)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$n++;
        }

        return $candidate;
    }
}
