<?php

namespace App\Services\Events;

use App\Models\FestCompetitionArea;
use App\Models\FestEligibilityRule;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates fest_eligibility_rules for a student. Empty rule set = no extra constraints.
 */
class FestEligibilityRuleEngine
{
    /** @return list<string> */
    public function validateStudent(Student $student, FestEvent $event, FestEventItem $item): array
    {
        if (! Schema::hasTable('fest_eligibility_rules')) {
            return [];
        }

        $rules = $this->rulesFor($event, $item);
        if ($rules->isEmpty()) {
            return [];
        }

        $errors = [];
        $byGroup = $rules->groupBy(fn (FestEligibilityRule $r) => (int) $r->logic_group);

        foreach ($byGroup as $groupRules) {
            $groupErrors = [];
            foreach ($groupRules as $rule) {
                $msg = $this->evaluate($rule, $student, $event, $item);
                if ($msg) {
                    $groupErrors[] = $msg;
                }
            }

            // Within a group: AND — if any rule fails, group fails.
            // Across groups: OR — student passes if any group fully passes.
            if ($groupErrors === []) {
                return [];
            }

            $errors = array_merge($errors, $groupErrors);
        }

        return array_values(array_unique($errors));
    }

    /** @return \Illuminate\Support\Collection<int, FestEligibilityRule> */
    private function rulesFor(FestEvent $event, FestEventItem $item)
    {
        $scopes = [
            [FestEligibilityRule::SCOPE_EVENT, $event->id],
            [FestEligibilityRule::SCOPE_ITEM, $item->id],
        ];

        if ($item->area_id) {
            $scopes[] = [FestEligibilityRule::SCOPE_AREA, $item->area_id];
        }

        return FestEligibilityRule::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) use ($scopes) {
                foreach ($scopes as [$type, $id]) {
                    $q->orWhere(fn ($inner) => $inner->where('scope_type', $type)->where('scope_id', $id));
                }
            })
            ->orderBy('logic_group')
            ->orderBy('sort_order')
            ->get();
    }

    private function evaluate(FestEligibilityRule $rule, Student $student, FestEvent $event, FestEventItem $item): ?string
    {
        $value = $rule->value_json ?? [];
        $op = $rule->operator ?: 'in';

        return match ($rule->rule_type) {
            'audience' => $this->audience($value, $student),
            'gender' => $this->gender($value, $op, $student),
            'class_group' => $this->listMatch($value, $op, $item->class_group, 'class category'),
            'age_group' => $this->listMatch($value, $op, $item->age_group, 'age group'),
            'kids_band' => $this->listMatch($value, $op, $item->kids_band, 'kids band'),
            'require_verified' => (! empty($value['required']) && ! $student->isVerified())
                ? 'must be Sahodaya-verified for this rule.'
                : null,
            'school' => $this->school($value, $op, $student),
            'region' => $this->region($value, $op, $student),
            'custom_ids' => $this->customIds($value, $op, $student),
            'require_prior_qualification' => null, // handled by existing service when policy set
            default => null,
        };
    }

    private function audience(array $value, Student $student): ?string
    {
        $allowed = $value['audience'] ?? $value['in'] ?? ['student'];
        if (! is_array($allowed)) {
            $allowed = [$allowed];
        }

        if (in_array('student', $allowed, true) || in_array('any', $allowed, true)) {
            return null;
        }

        return 'this competition is not open to students.';
    }

    private function gender(array $value, string $op, Student $student): ?string
    {
        $allowed = $value['in'] ?? $value['values'] ?? [];
        if (! is_array($allowed) || $allowed === []) {
            return null;
        }

        $g = strtolower((string) ($student->gender ?? ''));
        $ok = in_array($g, array_map('strtolower', $allowed), true)
            || in_array('open', $allowed, true)
            || in_array('mixed', $allowed, true);

        if ($op === 'not_in') {
            $ok = ! $ok;
        }

        return $ok ? null : 'gender does not match the eligibility rule.';
    }

    private function listMatch(array $value, string $op, ?string $actual, string $label): ?string
    {
        $allowed = $value['in'] ?? $value['values'] ?? [];
        if (! is_array($allowed) || $allowed === [] || $actual === null || $actual === '') {
            return null;
        }

        $ok = in_array($actual, $allowed, true);
        if ($op === 'not_in') {
            $ok = ! $ok;
        }

        return $ok ? null : "{$label} does not match the eligibility rule.";
    }

    private function school(array $value, string $op, Student $student): ?string
    {
        $ids = $value['school_ids'] ?? $value['in'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $ok = in_array($student->tenant_id, $ids, true);
        if ($op === 'not_in') {
            $ok = ! $ok;
        }

        return $ok ? null : 'school is not eligible for this competition.';
    }

    private function region(array $value, string $op, Student $student): ?string
    {
        $regionIds = $value['region_ids'] ?? $value['in'] ?? [];
        if (! is_array($regionIds) || $regionIds === []) {
            return null;
        }

        $school = $student->relationLoaded('tenant') ? $student->tenant : $student->tenant()->first();
        $schoolRegion = $school?->region_id ?? $school?->getSetting('region_id');
        $ok = $schoolRegion && in_array($schoolRegion, $regionIds, true);
        if ($op === 'not_in') {
            $ok = ! $ok;
        }

        return $ok ? null : 'school region is not eligible for this competition.';
    }

    private function customIds(array $value, string $op, Student $student): ?string
    {
        $ids = $value['student_ids'] ?? $value['in'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $ok = in_array($student->id, $ids, true) || in_array((string) $student->id, array_map('strval', $ids), true);
        if ($op === 'not_in') {
            $ok = ! $ok;
        }

        return $ok ? null : 'student is not on the allowed list.';
    }
}
