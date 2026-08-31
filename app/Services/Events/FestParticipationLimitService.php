<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

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

    /**
     * A group/team item counts only toward the group quota — never on-stage/off-stage,
     * regardless of its stage_type. Group participation is a different kind of
     * commitment (whole-squad, not one more individual slot), so it's the exclusive
     * bucket once participant_type is group/team; stage_type only matters for items
     * that aren't. Single source of truth for this classification — every quota check
     * below (school, student, combo-profile) and the usage badges all read through
     * this instead of re-deriving the same three flags independently, which is what let
     * on-stage-group items silently double-count against both buckets before.
     *
     * @return array{on_stage: bool, off_stage: bool, group: bool, offstage_writing: bool, offstage_drawing: bool}
     */
    private function itemDimensions(?FestEventItem $item): array
    {
        if (! $item) {
            return ['on_stage' => false, 'off_stage' => false, 'group' => false, 'offstage_writing' => false, 'offstage_drawing' => false];
        }

        $isGroup = $item->isTeamItem();
        $isOffStage = ! $isGroup && ($item->stage_type ?? '') === 'off_stage';

        return [
            'on_stage' => ! $isGroup && ($item->stage_type ?? '') === 'on_stage',
            'off_stage' => $isOffStage,
            'group' => $isGroup,
            'offstage_writing' => $isOffStage && ($item->category ?? '') === 'literary',
            'offstage_drawing' => $isOffStage && ($item->category ?? '') === 'fine_arts',
        ];
    }

    /**
     * Per-student limit-usage rows for the whole school — one row per student with at
     * least one active registration, each showing on-stage, off-stage, their combined
     * "individual" total, and the group count against the resolved policy limits.
     * Reuses itemDimensions()/countableStatuses() so a row's "exceeds" flag agrees with
     * what validateStudent() would have blocked at registration time, rather than
     * re-deriving the classification separately. Search matches student name or reg no.
     *
     * @return list<array<string, mixed>>
     */
    public function studentLimitReportRows(string $schoolId, ?string $search = null): array
    {
        // $this->event is already the root event (the controller constructs this service
        // with $event->rootEvent()) — the scheme itself is configured on the root's
        // fee_settings, so this is safe to resolve once here rather than per item.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $this->event);

        $registrations = FestRegistration::whereIn('event_id', $this->scopeEventIds())
            ->where('school_id', $schoolId)
            ->active()
            ->with(['item', 'participants' => fn ($q) => $q
                ->where('participant_role', 'performer')
                ->whereNotNull('student_id')
                ->with('student:id,name,reg_no')])
            ->get();

        $byStudent = [];
        foreach ($registrations as $reg) {
            foreach ($reg->participants as $participant) {
                $student = $participant->student;
                if (! $student) {
                    continue;
                }
                $byStudent[$student->id]['student'] ??= $student;
                $byStudent[$student->id]['regs'][] = $reg;
            }
        }

        $rows = [];
        foreach ($byStudent as $studentId => $entry) {
            $student = $entry['student'];
            if ($search) {
                $needle = strtolower($search);
                $haystack = strtolower(($student->name ?? '').' '.($student->reg_no ?? ''));
                if (! str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $policy = $this->policyFor($entry['regs'][0]->item?->class_group ?? null);
            $countable = $this->countableStatuses($policy);

            $onStageUsed = 0;
            $offStageUsed = 0;
            $groupUsed = 0;
            $totalUsed = 0;
            $items = [];

            foreach ($entry['regs'] as $reg) {
                $dims = $this->itemDimensions($reg->item);
                $isCountable = in_array($reg->status, $countable, true);

                if ($isCountable) {
                    if ($dims['on_stage']) {
                        $onStageUsed++;
                    }
                    if ($dims['off_stage']) {
                        $offStageUsed++;
                    }
                    if ($dims['group']) {
                        $groupUsed++;
                    }
                    if (! $dims['group'] && $reg->item && ! $this->excludedFromTotalCount($reg->item)) {
                        $totalUsed++;
                    }
                }

                $items[] = [
                    'item_id'    => $reg->item_id,
                    'item_title' => $reg->item?->title,
                    'category_label' => \App\Support\FestClassGroupScheme::resolveItemLabel($classGroupLabels, $reg->item?->class_group),
                    'dimension'  => $dims['group'] ? 'group' : ($dims['on_stage'] ? 'on_stage' : ($dims['off_stage'] ? 'off_stage' : null)),
                    'status'     => $reg->status,
                    'countable'  => $isCountable,
                ];
            }

            $onStageLimit = $policy['max_onstage_per_student'] ?? null;
            $offStageLimit = $policy['max_offstage_per_student'] ?? null;
            $individualLimit = (filled($onStageLimit) || filled($offStageLimit))
                ? (int) ($onStageLimit ?: 0) + (int) ($offStageLimit ?: 0)
                : null;

            $dimension = fn (int $used, $limit) => [
                'used'    => $used,
                'limit'   => filled($limit) ? (int) $limit : null,
                'exceeds' => filled($limit) && $used > (int) $limit,
            ];

            $onStage = $dimension($onStageUsed, $onStageLimit);
            $offStage = $dimension($offStageUsed, $offStageLimit);
            $individual = $dimension($onStageUsed + $offStageUsed, $individualLimit);
            $group = $dimension($groupUsed, $policy['max_group_per_student'] ?? null);
            $total = $dimension($totalUsed, $policy['max_total_per_student'] ?? null);

            $rows[] = [
                'student_id'  => (int) $studentId,
                'name'        => $student->name,
                'reg_no'      => $student->reg_no,
                'on_stage'    => $onStage,
                'off_stage'   => $offStage,
                'individual'  => $individual,
                'group'       => $group,
                'total'       => $total,
                'exceeds_any' => $onStage['exceeds'] || $offStage['exceeds'] || $individual['exceeds'] || $group['exceeds'] || $total['exceeds'],
                'items'       => $items,
            ];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return array_values($rows);
    }

    /** @param list<array<string, mixed>> $rows */
    public function summarizeStudentLimitRows(array $rows): array
    {
        $collection = collect($rows);

        return [
            'total_students'      => $collection->count(),
            'exceeding_students'  => $collection->filter(fn ($r) => $r['exceeds_any'])->count(),
            'exceeding_on_stage'  => $collection->filter(fn ($r) => $r['on_stage']['exceeds'])->count(),
            'exceeding_off_stage' => $collection->filter(fn ($r) => $r['off_stage']['exceeds'])->count(),
            'exceeding_individual' => $collection->filter(fn ($r) => $r['individual']['exceeds'])->count(),
            'exceeding_group'     => $collection->filter(fn ($r) => $r['group']['exceeds'])->count(),
            'exceeding_total'     => $collection->filter(fn ($r) => $r['total']['exceeds'])->count(),
        ];
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
     *                                       registration (not a brand new one), pass its
     *                                       id so its own current participants/entry
     *                                       aren't double-counted against school/student
     *                                       quotas or the "already has an entry" check.
     * @return list<string>
     */
    public function validateRegistration(FestEventItem $item, string $schoolId, array $studentIds, array $standbyIds = [], ?int $excludeRegistrationId = null): array
    {
        $errors = [];
        $policy = $this->policyFor($item->class_group);

        $maxPerSchool = (int) ($item->max_per_school ?? 1);
        if ($maxPerSchool > 1) {
            $itemCount = FestRegistration::whereIn('event_id', $this->scopeEventIds())
                ->where('school_id', $schoolId)
                ->whereIn('item_id', $this->equivalentItemIds($item))
                ->whereIn('status', $this->countableStatuses($policy))
                ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
                ->count();
            if ($itemCount >= $maxPerSchool) {
                $errors[] = "Maximum {$maxPerSchool} entries per school for this item.";
            }
        } elseif (($policy['one_entry_per_item_per_school'] ?? true) && $this->schoolHasItemEntry($schoolId, $item->id, $policy, $excludeRegistrationId)) {
            $errors[] = 'Your school already has an entry for this item.';
        }

        $errors = array_merge($errors, $this->validateHeadCapacity($item, $policy, $schoolId, $excludeRegistrationId));

        $regs = $this->schoolRegistrations($schoolId, $policy, $excludeRegistrationId);
        $dims = $this->itemDimensions($item);
        $isOnStage = $dims['on_stage'];
        $isOffStage = $dims['off_stage'];
        $isGroup = $dims['group'];

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
    private function validateHeadCapacity(FestEventItem $item, array $policy, string $schoolId, ?int $excludeRegistrationId = null): array
    {
        // Sports events: enforce max_teams and max_participants per-school and per-item.
        if ($this->event->event_type === 'sports') {
            return $this->validateEventCapacity($item, $policy, $schoolId, $excludeRegistrationId);
        }

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
                    ->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES))
                ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
                ->count();

            if ($teamCount >= $maxTeams) {
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
                        ->orWhereNotIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES);
                }))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->count();

        if ($participantCount >= $maxParticipants) {
            return ["{$head->name} has reached its participant cap ({$maxParticipants})."];
        }

        return [];
    }

    /**
     * Enforce FestEvent.max_participants / max_teams for unified sports events.
     *
     * @return list<string>
     */
    private function validateEventCapacity(FestEventItem $item, array $policy, string $schoolId, ?int $excludeRegistrationId = null): array
    {
        $statuses = $this->countableStatuses($policy);
        $isTeam = $item->isTeamItem();
        $itemIds = $this->equivalentItemIds($item);

        if ($isTeam) {
            $maxTeams = (int) ($item->head?->max_teams ?? $this->event->max_teams ?? 0);
            if ($maxTeams <= 0) {
                return [];
            }

            $teamCount = FestRegistration::where('event_id', $this->event->id)
                ->where('school_id', $schoolId)
                ->whereIn('item_id', $itemIds)
                ->whereIn('status', $statuses)
                ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
                ->count();

            if ($teamCount >= $maxTeams) {
                return ["Team cap reached for this category ({$maxTeams})."];
            }

            return [];
        }

        $maxParticipants = (int) ($item->head?->max_participants ?? $this->event->max_participants ?? 0);
        if ($maxParticipants <= 0) {
            return [];
        }

        $participantCount = FestRegistration::where('event_id', $this->event->id)
            ->where('school_id', $schoolId)
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', $statuses)
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->count();

        if ($participantCount >= $maxParticipants) {
            return ["Participant cap reached for this category ({$maxParticipants})."];
        }

        return [];
    }

    /** Whether this item's Event Head is at capacity (active regs only — excludes waitlisted). */
    public function isHeadAtCapacity(FestEventItem $item, ?string $schoolId = null): bool
    {
        if ($this->event->event_type === 'sports') {
            return $this->isEventAtCapacity($item, $schoolId);
        }

        if (! $item->head_id) {
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
                    ->whereIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES))
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
                        ->orWhereNotIn('participant_type', FestTeamSquadRules::MULTI_PERSON_TYPES);
                }))
            ->count();

        return $participantCount >= $maxParticipants;
    }

    public function isEventAtCapacity(FestEventItem $item, ?string $schoolId = null): bool
    {
        if ($this->event->event_type !== 'sports') {
            return false;
        }

        $statuses = ['submitted', 'pending_approval', 'approved'];
        $isTeam = $item->isTeamItem();
        $itemIds = $this->equivalentItemIds($item);

        if ($isTeam) {
            $maxTeams = (int) ($item->head?->max_teams ?? $this->event->max_teams ?? 0);
            if ($maxTeams <= 0) {
                return false;
            }

            $teamCount = FestRegistration::where('event_id', $this->event->id)
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->whereIn('item_id', $itemIds)
                ->whereIn('status', $statuses)
                ->count();

            return $teamCount >= $maxTeams;
        }

        $maxParticipants = (int) ($item->head?->max_participants ?? $this->event->max_participants ?? 0);
        if ($maxParticipants <= 0) {
            return false;
        }

        $participantCount = FestRegistration::where('event_id', $this->event->id)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', $statuses)
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

            $dims = $this->itemDimensions($item);

            if ($dims['on_stage']) {
                $counts['onstage']++;
            }
            if ($dims['off_stage']) {
                $counts['offstage']++;
            }
            if ($dims['group']) {
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

        $dims = $this->itemDimensions($item);
        $isOnStage = $dims['on_stage'];
        $isOffStage = $dims['off_stage'];
        $isGroup = $dims['group'];

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

        if ($dims['offstage_writing'] && ! empty($policy['max_offstage_writing_per_student'])) {
            $count = $this->filterRegs($studentRegs, 'offstage_writing')->count() + 1;
            if ($count > (int) $policy['max_offstage_writing_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_offstage_writing_per_student']} off-stage writing items.";
            }
        }

        if ($dims['offstage_drawing'] && ! empty($policy['max_offstage_drawing_per_student'])) {
            $count = $this->filterRegs($studentRegs, 'offstage_drawing')->count() + 1;
            if ($count > (int) $policy['max_offstage_drawing_per_student']) {
                $name = Student::where('id', $studentId)->value('name') ?? 'Student';
                $errors[] = "{$name} exceeds max {$policy['max_offstage_drawing_per_student']} off-stage drawing items.";
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
        $item = FestEventItem::find($itemId);

        return FestRegistration::whereIn('event_id', $this->scopeEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('item_id', $item ? $this->equivalentItemIds($item) : [$itemId])
            ->whereIn('status', $this->countableStatuses($policy))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->exists();
    }

    /** @return Collection<int, FestRegistration> */
    private function schoolRegistrations(string $schoolId, array $policy, ?int $excludeRegistrationId = null)
    {
        return FestRegistration::whereIn('event_id', $this->scopeEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', $this->countableStatuses($policy))
            ->when($excludeRegistrationId, fn ($q) => $q->where('id', '!=', $excludeRegistrationId))
            ->with('item')
            ->get();
    }

    /** @return Collection<int, FestRegistration> */
    private function studentRegistrations(int $studentId, string $schoolId, array $policy, ?int $excludeRegistrationId = null)
    {
        $registrationIds = FestParticipant::where('student_id', $studentId)
            ->where('participant_role', 'performer')
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $this->scopeEventIds())
                ->where('school_id', $schoolId)
                ->whereIn('status', $this->countableStatuses($policy)))
            ->when($excludeRegistrationId, fn ($q) => $q->where('registration_id', '!=', $excludeRegistrationId))
            ->pluck('registration_id');

        return FestRegistration::whereIn('id', $registrationIds)->with('item')->get();
    }

    /** @return list<int> */
    private function scopeEventIds(): array
    {
        if (! $this->event->parent_event_id) {
            return $this->event->reportableEventIds();
        }

        $hub = FestEvent::find($this->event->parent_event_id);

        return $hub && ($hub->conduct_mode ?? 'standard') === 'partitioned'
            ? $hub->reportableEventIds()
            : [$this->event->id];
    }

    /** @return list<int> */
    private function equivalentItemIds(FestEventItem $item): array
    {
        $rootId = (int) ($item->inherited_from_item_id ?: $item->id);

        return FestEventItem::query()
            ->whereIn('event_id', $this->scopeEventIds())
            ->where(function ($query) use ($item, $rootId) {
                $query->where('id', $rootId)
                    ->orWhere('inherited_from_item_id', $rootId);

                if (filled($item->item_code)) {
                    $query->orWhere('item_code', $item->item_code);
                }
            })
            ->pluck('id')
            ->push($item->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @param Collection<int, FestRegistration> $regs */
    private function filterRegs($regs, string $dimension)
    {
        return $regs->filter(fn (FestRegistration $r) => $this->itemDimensions($r->item)[$dimension] ?? false);
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

    /** @param Collection<int, FestRegistration> $regs */
    private function countableTotalForStudent($regs): int
    {
        return $regs->filter(function (FestRegistration $r) {
            $discipline = $r->item?->sport_discipline;
            $dims = $this->itemDimensions($r->item);

            return ! $dims['group'] && ! in_array($discipline, ['relay', 'march_past'], true);
        })->count();
    }
}
