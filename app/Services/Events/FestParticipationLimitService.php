<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;

class FestParticipationLimitService
{
    public function __construct(
        public FestEvent $event,
        private ?FestParticipationPolicyService $policyService = null,
    ) {
        $this->policyService ??= app(FestParticipationPolicyService::class);
    }

    /** @return array<string, mixed> */
    public function policyFor(?string $classGroup = null): array
    {
        return $this->policyService->resolveForEvent($this->event, $classGroup);
    }

    /** @return array{used: array<string, int>, limits: array<string, mixed>} */
    public function usageForSchool(string $schoolId, ?string $classGroup = null): array
    {
        $policy = $this->policyFor($classGroup);
        $regs = $this->schoolRegistrations($schoolId, $policy);

        return [
            'used' => [
                'total' => $regs->count(),
                'on_stage' => $this->filterRegs($regs, 'on_stage')->count(),
                'off_stage' => $this->filterRegs($regs, 'off_stage')->count(),
                'group' => $this->filterRegs($regs, 'group')->count(),
            ],
            'limits' => $policy,
        ];
    }

    /** @return list<string> */
    public function validateRegistration(FestEventItem $item, string $schoolId, array $studentIds, array $standbyIds = []): array
    {
        $errors = [];
        $policy = $this->policyFor($item->class_group);

        if (($policy['one_entry_per_item_per_school'] ?? true) && $this->schoolHasItemEntry($schoolId, $item->id, $policy)) {
            $errors[] = 'Your school already has an entry for this item.';
        }

        $maxPerSchool = (int) ($item->max_per_school ?? 1);
        if ($maxPerSchool > 0) {
            $itemCount = FestRegistration::where('event_id', $this->event->id)
                ->where('school_id', $schoolId)
                ->where('item_id', $item->id)
                ->whereIn('status', $this->countableStatuses($policy))
                ->count();
            if ($itemCount >= $maxPerSchool) {
                $errors[] = "Maximum {$maxPerSchool} entr".($maxPerSchool === 1 ? 'y' : 'ies').' per school for this item.';
            }
        }

        $regs = $this->schoolRegistrations($schoolId, $policy);
        $isOnStage = ($item->stage_type ?? '') === 'on_stage';
        $isOffStage = ($item->stage_type ?? '') === 'off_stage';
        $isGroup = in_array($item->participant_type, ['group', 'team'], true);

        if ($isOnStage && ! empty($policy['max_onstage_per_school'])) {
            $count = $this->filterRegs($regs, 'on_stage')->count() + 1;
            if ($count > (int) $policy['max_onstage_per_school']) {
                $errors[] = "School exceeds max {$policy['max_onstage_per_school']} on-stage entries.";
            }
        }

        if ($isOffStage && ! empty($policy['max_offstage_per_school'])) {
            $count = $this->filterRegs($regs, 'off_stage')->count() + 1;
            if ($count > (int) $policy['max_offstage_per_school']) {
                $errors[] = "School exceeds max {$policy['max_offstage_per_school']} off-stage entries.";
            }
        }

        if ($isGroup && ! empty($policy['max_group_per_school'])) {
            $count = $this->filterRegs($regs, 'group')->count() + 1;
            if ($count > (int) $policy['max_group_per_school']) {
                $errors[] = "School exceeds max {$policy['max_group_per_school']} group entries.";
            }
        }

        $performerIds = array_values(array_diff($studentIds, $standbyIds));

        foreach ($performerIds as $sid) {
            $errors = array_merge($errors, $this->validateStudent($sid, $item, $schoolId, $policy));
        }

        $errors = array_merge(
            $errors,
            app(FestComboRuleService::class)->validate($this->event, $item, $schoolId, $performerIds)
        );

        if ($isGroup && count($standbyIds) > 2) {
            $errors[] = 'Maximum 2 standby participants allowed per group item.';
        }

        return $errors;
    }

    /** @return list<string> */
    private function validateStudent(int $studentId, FestEventItem $item, string $schoolId, array $policy): array
    {
        $errors = [];
        $studentRegs = $this->studentRegistrations($studentId, $schoolId, $policy);

        $isOnStage = ($item->stage_type ?? '') === 'on_stage';
        $isOffStage = ($item->stage_type ?? '') === 'off_stage';
        $isGroup = in_array($item->participant_type, ['group', 'team'], true);

        if ($isOnStage && ! empty($policy['max_onstage_per_student'])) {
            $count = $this->filterRegs($studentRegs, 'on_stage')->count() + 1;
            if ($count > (int) $policy['max_onstage_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_onstage_per_student']} on-stage items.";
            }
        }

        if ($isOffStage && ! empty($policy['max_offstage_per_student'])) {
            $count = $this->filterRegs($studentRegs, 'off_stage')->count() + 1;
            if ($count > (int) $policy['max_offstage_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_offstage_per_student']} off-stage items.";
            }
        }

        if ($isGroup && ! empty($policy['max_group_per_student'])) {
            $count = $this->filterRegs($studentRegs, 'group')->count() + 1;
            if ($count > (int) $policy['max_group_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_group_per_student']} group items.";
            }
        }

        if (! empty($policy['max_total_per_student'])) {
            $count = $studentRegs->count() + 1;
            if ($count > (int) $policy['max_total_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_total_per_student']} total items.";
            }
        }

        return $errors;
    }

    private function schoolHasItemEntry(string $schoolId, int $itemId, array $policy): bool
    {
        return FestRegistration::where('event_id', $this->event->id)
            ->where('school_id', $schoolId)
            ->where('item_id', $itemId)
            ->whereIn('status', $this->countableStatuses($policy))
            ->exists();
    }

    /** @return \Illuminate\Support\Collection<int, FestRegistration> */
    private function schoolRegistrations(string $schoolId, array $policy)
    {
        return FestRegistration::where('event_id', $this->event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', $this->countableStatuses($policy))
            ->with('item')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, FestRegistration> */
    private function studentRegistrations(int $studentId, string $schoolId, array $policy)
    {
        $registrationIds = FestParticipant::where('student_id', $studentId)
            ->where('participant_role', 'performer')
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', $this->countableStatuses($policy)))
            ->pluck('registration_id');

        return FestRegistration::whereIn('id', $registrationIds)->with('item')->get();
    }

    /** @param \Illuminate\Support\Collection<int, FestRegistration> $regs */
    private function filterRegs($regs, string $dimension)
    {
        return $regs->filter(function (FestRegistration $r) use ($dimension) {
            $item = $r->item;
            if (! $item) {
                return false;
            }

            return match ($dimension) {
                'on_stage' => ($item->stage_type ?? '') === 'on_stage',
                'off_stage' => ($item->stage_type ?? '') === 'off_stage',
                'group' => in_array($item->participant_type, ['group', 'team'], true),
                default => false,
            };
        });
    }

    /** @return list<string> */
    private function countableStatuses(array $policy): array
    {
        if ($policy['count_submitted_registrations'] ?? true) {
            return ['submitted', 'approved'];
        }

        return ['approved'];
    }
}
