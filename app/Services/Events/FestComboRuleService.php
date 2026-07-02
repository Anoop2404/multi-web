<?php

namespace App\Services\Events;

use App\Models\FestCombinationRule;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\Student;

class FestComboRuleService
{
    /** @return list<string> */
    public function validate(FestEvent $event, FestEventItem $item, string $schoolId, array $studentIds): array
    {
        $errors = [];
        $rule = $this->resolveRule($event, $schoolId, $item->class_group);

        if (! $rule) {
            return [];
        }

        $regs = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', ['submitted', 'approved'])
            ->with('item')
            ->get();

        $category = $this->itemCategory($item);

        if ($rule->max_arts_events && $category === 'arts') {
            $count = $this->countCategory($regs, 'arts') + 1;
            if ($count > $rule->max_arts_events) {
                $errors[] = "School exceeds max {$rule->max_arts_events} arts events.";
            }
        }

        if ($rule->max_sports_events && $category === 'sports') {
            $count = $this->countCategory($regs, 'sports') + 1;
            if ($count > $rule->max_sports_events) {
                $errors[] = "School exceeds max {$rule->max_sports_events} sports events.";
            }
        }

        if ($rule->max_common_events && $category === 'common') {
            $count = $this->countCategory($regs, 'common') + 1;
            if ($count > $rule->max_common_events) {
                $errors[] = "School exceeds max {$rule->max_common_events} common-pool events.";
            }
        }

        if ($rule->max_on_stage && ($item->stage_type ?? '') === 'on_stage') {
            $count = $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'on_stage')->count() + 1;
            if ($count > $rule->max_on_stage) {
                $errors[] = "School exceeds max {$rule->max_on_stage} on-stage entries.";
            }
        }

        if ($rule->max_off_stage && ($item->stage_type ?? '') === 'off_stage') {
            $count = $regs->filter(fn ($r) => ($r->item?->stage_type ?? '') === 'off_stage')->count() + 1;
            if ($count > $rule->max_off_stage) {
                $errors[] = "School exceeds max {$rule->max_off_stage} off-stage entries.";
            }
        }

        if ($rule->max_group && in_array($item->participant_type, ['group', 'team'], true)) {
            $count = $regs->filter(fn ($r) => in_array($r->item?->participant_type, ['group', 'team'], true))->count() + 1;
            if ($count > $rule->max_group) {
                $errors[] = "School exceeds max {$rule->max_group} group entries.";
            }
        }

        return $errors;
    }

    private function resolveRule(FestEvent $event, string $schoolId, ?string $classGroup): ?FestCombinationRule
    {
        return FestCombinationRule::where('event_id', $event->id)
            ->where(function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)->orWhereNull('school_id');
            })
            ->where(function ($q) use ($classGroup) {
                $q->where('class_group', $classGroup)->orWhereNull('class_group');
            })
            ->orderByRaw('school_id IS NULL ASC')
            ->orderByRaw('class_group IS NULL ASC')
            ->first();
    }

    private function itemCategory(FestEventItem $item): string
    {
        if (($item->category ?? '') === 'sports' || ($item->sport_discipline ?? null)) {
            return 'sports';
        }

        $criteria = $item->criteria_json ?? [];
        if (($criteria['arts_category'] ?? null) === 'common') {
            return 'common';
        }

        return 'arts';
    }

    /** @param  \Illuminate\Support\Collection<int, FestRegistration>  $regs */
    private function countCategory($regs, string $category): int
    {
        return $regs->filter(function (FestRegistration $r) use ($category) {
            if (! $r->item) {
                return false;
            }

            return $this->itemCategory($r->item) === $category;
        })->count();
    }
}
