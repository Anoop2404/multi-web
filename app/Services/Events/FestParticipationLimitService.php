<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
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

    /**
     * @param  ?int  $excludeRegistrationId  When re-validating an EDIT of an existing
     *                                        registration (not a brand new one), pass its
     *                                        id so its own current participants/entry
     *                                        aren't double-counted against school/student
     *                                        quotas or the "already has an entry" check.
     * @return list<string>
     */
    public function validateRegistration(FestEventItem $item, string $schoolId, array $studentIds, array $standbyIds = [], ?int $excludeRegistrationId = null): array
    {
        $errors = [];
        $policy = $this->policyFor($item->class_group);

        if (($policy['one_entry_per_item_per_school'] ?? true) && $this->schoolHasItemEntry($schoolId, $item->id, $policy, $excludeRegistrationId)) {
            $errors[] = 'Your school already has an entry for this item.';
        }

        $maxPerSchool = (int) ($item->max_per_school ?? 1);
        if ($maxPerSchool > 0) {
            $itemCount = FestRegistration::where('event_id', $this->event->id)
                ->where('school_id', $schoolId)
                ->where('item_id', $item->id)
                ->whereIn('status', $this->countableStatuses($policy))
                ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
                ->count();
            if ($itemCount >= $maxPerSchool) {
                $errors[] = "Maximum {$maxPerSchool} entr".($maxPerSchool === 1 ? 'y' : 'ies').' per school for this item.';
            }
        }

        $errors = array_merge($errors, $this->validateHeadCapacity($item, $policy, $excludeRegistrationId));

        $regs = $this->schoolRegistrations($schoolId, $policy, $excludeRegistrationId);
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
            $errors = array_merge($errors, $this->validateStudent($sid, $item, $schoolId, $policy, $excludeRegistrationId));
        }

        $errors = array_merge($errors, $this->validateComboProfiles($performerIds, $item, $schoolId, $policy, $excludeRegistrationId));

        $errors = array_merge(
            $errors,
            app(FestComboRuleService::class)->validate($this->event, $item, $schoolId, $performerIds)
        );

        if ($isGroup && count($standbyIds) > 2) {
            $errors[] = 'Maximum 2 standby participants allowed per group item.';
        }

        return $errors;
    }

    /**
     * Enforce FestItemHead.max_participants / max_teams when set (> 0).
     * null/0 = unlimited (same convention as max_per_school).
     *
     * @return list<string>
     */
    private function validateHeadCapacity(FestEventItem $item, array $policy, ?int $excludeRegistrationId = null): array
    {
        if (! $item->head_id) {
            return [];
        }

        $head = $item->relationLoaded('head')
            ? $item->head
            : FestItemHead::find($item->head_id);

        if (! $head) {
            return [];
        }

        $statuses = $this->countableStatuses($policy);
        $isTeam = $item->isTeamItem();

        if ($isTeam) {
            $maxTeams = (int) ($head->max_teams ?? 0);
            if ($maxTeams <= 0) {
                return [];
            }

            $teamCount = FestRegistration::where('event_id', $this->event->id)
                ->whereIn('status', $statuses)
                ->whereHas('item', fn ($q) => $q
                    ->where('head_id', $head->id)
                    ->whereIn('participant_type', ['team', 'group']))
                ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
                ->count();

            if ($teamCount >= $maxTeams) {
                // Sports Event Heads: allow waitlist instead of hard reject (FRD-04 v2).
                if ($this->event->event_type === 'sports') {
                    return [];
                }

                return ["{$head->name} has reached its team cap ({$maxTeams})."];
            }

            return [];
        }

        $maxParticipants = (int) ($head->max_participants ?? 0);
        if ($maxParticipants <= 0) {
            return [];
        }

        $participantCount = FestRegistration::where('event_id', $this->event->id)
            ->whereIn('status', $statuses)
            ->whereHas('item', fn ($q) => $q
                ->where('head_id', $head->id)
                ->where(function ($q) {
                    $q->whereNull('participant_type')
                        ->orWhereNotIn('participant_type', ['team', 'group']);
                }))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->count();

        if ($participantCount >= $maxParticipants) {
            if ($this->event->event_type === 'sports') {
                return [];
            }

            return ["{$head->name} has reached its participant cap ({$maxParticipants})."];
        }

        return [];
    }

    /** Whether this item's Event Head is at capacity (active regs only — excludes waitlisted). */
    public function isHeadAtCapacity(FestEventItem $item): bool
    {
        if ($this->event->event_type !== 'sports' || ! $item->head_id) {
            return false;
        }

        $head = $item->relationLoaded('head') ? $item->head : FestItemHead::find($item->head_id);
        if (! $head) {
            return false;
        }

        $statuses = ['submitted', 'pending_approval', 'approved'];
        $isTeam = $item->isTeamItem();

        if ($isTeam) {
            $maxTeams = (int) ($head->max_teams ?? 0);
            if ($maxTeams <= 0) {
                return false;
            }

            $teamCount = FestRegistration::where('event_id', $this->event->id)
                ->whereIn('status', $statuses)
                ->whereHas('item', fn ($q) => $q
                    ->where('head_id', $head->id)
                    ->whereIn('participant_type', ['team', 'group']))
                ->count();

            return $teamCount >= $maxTeams;
        }

        $maxParticipants = (int) ($head->max_participants ?? 0);
        if ($maxParticipants <= 0) {
            return false;
        }

        $participantCount = FestRegistration::where('event_id', $this->event->id)
            ->whereIn('status', $statuses)
            ->whereHas('item', fn ($q) => $q
                ->where('head_id', $head->id)
                ->where(function ($q) {
                    $q->whereNull('participant_type')
                        ->orWhereNotIn('participant_type', ['team', 'group']);
                }))
            ->count();

        return $participantCount >= $maxParticipants;
    }

    /** @return list<string> */
    private function validateComboProfiles(array $performerIds, FestEventItem $item, string $schoolId, array $policy, ?int $excludeRegistrationId = null): array
    {
        $profiles = $policy['combo_profiles'] ?? null;
        if (! is_array($profiles) || $profiles === []) {
            return [];
        }

        $errors = [];
        foreach ($performerIds as $studentId) {
            $studentRegs = $this->studentRegistrations($studentId, $schoolId, $policy, $excludeRegistrationId);
            $counts = [
                'onstage' => $this->filterRegs($studentRegs, 'on_stage')->count(),
                'offstage' => $this->filterRegs($studentRegs, 'off_stage')->count(),
                'group' => $this->filterRegs($studentRegs, 'group')->count(),
            ];

            $isOnStage = ($item->stage_type ?? '') === 'on_stage';
            $isOffStage = ($item->stage_type ?? '') === 'off_stage';
            $isGroup = in_array($item->participant_type, ['group', 'team'], true);

            if ($isOnStage) {
                $counts['onstage']++;
            }
            if ($isOffStage) {
                $counts['offstage']++;
            }
            if ($isGroup) {
                $counts['group']++;
            }

            $satisfied = false;
            foreach ($profiles as $profile) {
                if ($counts['onstage'] <= (int) ($profile['onstage'] ?? 99)
                    && $counts['offstage'] <= (int) ($profile['offstage'] ?? 99)
                    && $counts['group'] <= (int) ($profile['group'] ?? 99)
                    && ($counts['onstage'] + $counts['offstage'] + $counts['group']) <=
                        ((int) ($profile['onstage'] ?? 0) + (int) ($profile['offstage'] ?? 0) + (int) ($profile['group'] ?? 0))
                ) {
                    $satisfied = true;
                    break;
                }
            }

            if (! $satisfied) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} does not satisfy any allowed MCS item combination profile.";
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private function validateStudent(int $studentId, FestEventItem $item, string $schoolId, array $policy, ?int $excludeRegistrationId = null): array
    {
        $errors = [];
        $studentRegs = $this->studentRegistrations($studentId, $schoolId, $policy, $excludeRegistrationId);

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

        if (! empty($policy['max_total_per_student']) && ! $this->excludedFromTotalCount($item)) {
            $count = $this->countableTotalForStudent($studentRegs) + 1;
            if ($count > (int) $policy['max_total_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_total_per_student']} total items.";
            }
        }

        return $errors;
    }

    private function schoolHasItemEntry(string $schoolId, int $itemId, array $policy, ?int $excludeRegistrationId = null): bool
    {
        return FestRegistration::where('event_id', $this->event->id)
            ->where('school_id', $schoolId)
            ->where('item_id', $itemId)
            ->whereIn('status', $this->countableStatuses($policy))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->exists();
    }

    /** @return \Illuminate\Support\Collection<int, FestRegistration> */
    private function schoolRegistrations(string $schoolId, array $policy, ?int $excludeRegistrationId = null)
    {
        return FestRegistration::where('event_id', $this->event->id)
            ->where('school_id', $schoolId)
            ->whereIn('status', $this->countableStatuses($policy))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->with('item')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, FestRegistration> */
    private function studentRegistrations(int $studentId, string $schoolId, array $policy, ?int $excludeRegistrationId = null)
    {
        $registrationIds = FestParticipant::where('student_id', $studentId)
            ->where('participant_role', 'performer')
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('school_id', $schoolId)
                ->whereIn('status', $this->countableStatuses($policy)))
            ->when($excludeRegistrationId, fn ($q) => $q->where('registration_id', '!=', $excludeRegistrationId))
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

    private function excludedFromTotalCount(FestEventItem $item): bool
    {
        return in_array($item->sport_discipline, ['relay', 'march_past'], true);
    }

    /** @param \Illuminate\Support\Collection<int, FestRegistration> $regs */
    private function countableTotalForStudent($regs): int
    {
        return $regs->filter(function (FestRegistration $r) {
            $discipline = $r->item?->sport_discipline;

            return ! in_array($discipline, ['relay', 'march_past'], true);
        })->count();
    }
}
