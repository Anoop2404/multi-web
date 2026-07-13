<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCompetitionArea;
use App\Models\FestEligibilityRule;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestEligibilityRuleController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rules = FestEligibilityRule::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->where(function ($q) use ($event) {
                $q->where(fn ($inner) => $inner
                    ->where('scope_type', FestEligibilityRule::SCOPE_EVENT)
                    ->where('scope_id', $event->id));

                $areaIds = FestCompetitionArea::where('event_id', $event->id)->pluck('id');
                if ($areaIds->isNotEmpty()) {
                    $q->orWhere(fn ($inner) => $inner
                        ->where('scope_type', FestEligibilityRule::SCOPE_AREA)
                        ->whereIn('scope_id', $areaIds));
                }

                $itemIds = FestEventItem::where('event_id', $event->id)->pluck('id');
                if ($itemIds->isNotEmpty()) {
                    $q->orWhere(fn ($inner) => $inner
                        ->where('scope_type', FestEligibilityRule::SCOPE_ITEM)
                        ->whereIn('scope_id', $itemIds));
                }
            })
            ->orderBy('scope_type')
            ->orderBy('logic_group')
            ->orderBy('sort_order')
            ->get();

        return $this->inertia('Sahodaya/Events/EligibilityRules/Index', $this->withFestNavContext([
            'event' => $event->only('id', 'title', 'event_type', 'status'),
            'rules' => $rules,
            'ruleTypes' => FestEligibilityRule::RULE_TYPES,
            'areas' => FestCompetitionArea::where('event_id', $event->id)->orderBy('sort_order')->get(['id', 'name']),
            'items' => FestEventItem::where('event_id', $event->id)->orderBy('title')->get(['id', 'title']),
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request, $event);

        FestEligibilityRule::create([
            ...$data,
            'tenant_id' => $this->sahodaya->id,
            'is_active' => true,
        ]);

        return back()->with('success', 'Eligibility rule added.');
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestEligibilityRule $eligibilityRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($eligibilityRule->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validated($request, $event);
        $eligibilityRule->update($data);

        return back()->with('success', 'Eligibility rule updated.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestEligibilityRule $eligibilityRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($eligibilityRule->tenant_id !== $this->sahodaya->id, 403);

        $eligibilityRule->delete();

        return back()->with('success', 'Eligibility rule removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, FestEvent $event): array
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in([
                FestEligibilityRule::SCOPE_EVENT,
                FestEligibilityRule::SCOPE_AREA,
                FestEligibilityRule::SCOPE_ITEM,
            ])],
            'scope_id' => 'required|integer',
            'rule_type' => ['required', Rule::in(array_keys(FestEligibilityRule::RULE_TYPES))],
            'operator' => 'nullable|in:in,not_in,eq',
            'value_json' => 'nullable|array',
            'logic_group' => 'nullable|integer|min:0|max:99',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        $this->assertScopeBelongs($event, $data['scope_type'], (int) $data['scope_id']);

        $data['operator'] = $data['operator'] ?? 'in';
        $data['logic_group'] = $data['logic_group'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function assertScopeBelongs(FestEvent $event, string $scopeType, int $scopeId): void
    {
        $ok = match ($scopeType) {
            FestEligibilityRule::SCOPE_EVENT => $scopeId === (int) $event->id,
            FestEligibilityRule::SCOPE_AREA => FestCompetitionArea::where('event_id', $event->id)->whereKey($scopeId)->exists(),
            FestEligibilityRule::SCOPE_ITEM => FestEventItem::where('event_id', $event->id)->whereKey($scopeId)->exists(),
            default => false,
        };

        abort_unless($ok, 422, 'Scope does not belong to this event.');
    }
}
