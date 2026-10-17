<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateClassCategory;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Support\FestStudentClassResolver;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

/** Shared checks for intake approval, materialized entries and result publication. */
class StateEligibilityService
{
    public const OK = 'ok';

    public const WRONG_CATEGORY = 'wrong_category';

    public const TEAM_SIZE = 'team_size';

    public const UNKNOWN_CLASS = 'unknown_class';

    public const NO_CATEGORY = 'no_category';

    public const WRONG_GENDER = 'wrong_gender';

    public const UNKNOWN_GENDER = 'unknown_gender';

    public const STUDENT_LIMIT = 'student_limit';

    public const DUPLICATE_ITEM = 'duplicate_item';

    public const UNKNOWN_IDENTITY = 'unknown_identity';

    /**
     * Check one registration.
     *
     * @return array{status: string, problems: list<array{code: string, message: string}>}
     */
    public function check(StateFestEvent $event, StateFestRegistration $registration, ?FestStateProgramItem $item = null, ?Collection $pool = null): array
    {
        $item ??= FestStateProgramItem::find($registration->item_id);
        $problems = [];

        if (! $item || $item->state_program_id !== $event->state_program_id) {
            return ['status' => self::NO_CATEGORY, 'problems' => [
                ['code' => self::NO_CATEGORY, 'message' => 'This entry is for an item that no longer exists.'],
            ]];
        }

        $participants = $registration->relationLoaded('participants')
            ? $registration->participants
            : $registration->participants()->get();

        $competing = $participants->filter(fn (StateFestParticipant $p) => $p->isCompeting());

        $problems = array_merge(
            $problems,
            $this->categoryProblems($event, $item, $competing),
            $this->teamSizeProblems($item, $competing),
            $this->genderProblems($item, $competing),
            $this->participationProblems($event, $registration, $competing, $pool),
        );

        return [
            'status' => $problems === [] ? self::OK : $problems[0]['code'],
            'problems' => $problems,
        ];
    }

    /**
     * Findings for active materialized entries. The same rules gate approvals and publication;
     * incomplete evidence is a warning unless the event requires verified eligibility.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function audit(StateFestEvent $event, array $filters = []): Collection
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)->get()->keyBy('id');

        return StateFestRegistration::where('state_event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $v) => $q->where('sahodaya_id', $v))
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->with('participants')
            ->get()
            ->map(function (StateFestRegistration $registration) use ($event, $items) {
                $result = $this->check($event, $registration, $items->get($registration->item_id));

                if ($result['status'] === self::OK) {
                    return null;
                }

                return [
                    'registration_id' => $registration->id,
                    'item_code' => $registration->item_code,
                    'item' => $items->get($registration->item_id)?->title,
                    'category' => $items->get($registration->item_id)?->class_group,
                    'sahodaya' => $registration->sahodaya_name ?: 'Unattributed',
                    'school' => $registration->school_name ?: $registration->school_id,
                    'participants' => $registration->participants
                        ->filter(fn ($p) => $p->isCompeting())
                        ->map(fn ($p) => ['name' => $p->student_name, 'class_name' => $p->class_name])->values(),
                    'status' => $result['status'],
                    'problems' => $result['problems'],
                ];
            })
            ->filter()->values();
    }

    /** @return array{checked: int, ok: int, problems: array<string, int>} */
    public function summary(StateFestEvent $event): array
    {
        $checked = StateFestRegistration::where('state_event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])->count();

        $problems = $this->audit($event)->groupBy('status')->map->count();

        return [
            'checked' => $checked,
            'ok' => $checked - $problems->sum(),
            'problems' => $problems->all(),
        ];
    }

    /** Unknown evidence remains visible; strict mode requires it to be corrected before approval. */
    public function blockingProblems(StateFestEvent $event, array $result): array
    {
        $warnings = [self::UNKNOWN_CLASS, self::UNKNOWN_GENDER, self::UNKNOWN_IDENTITY, self::NO_CATEGORY];

        return array_values(array_filter($result['problems'], fn ($problem) => ($event->settings['require_verified_eligibility'] ?? false)
            || ! in_array($problem['code'], $warnings, true)));
    }

    /** Validate a prospective batch against other approved entries, including its own pending rows. */
    public function entryApprovalProblems(StateFestEvent $event, Collection $entries): array
    {
        $others = StateQualifierEntry::where('status', 'approved')
            ->whereNotIn('id', $entries->pluck('id'))
            ->whereHas('intake', fn ($q) => $q->where('state_program_id', $event->state_program_id))
            ->with('intake')->get();
        $pool = $others->concat($entries)->map(fn ($entry) => $this->entryRegistration($event, $entry));
        $errors = [];
        foreach ($entries as $entry) {
            $registration = $this->entryRegistration($event, $entry);
            foreach ($this->blockingProblems($event, $this->check($event, $registration, null, $pool)) as $problem) {
                $errors[] = $entry->student_name.': '.$problem['message'];
            }
        }

        return array_values(array_unique($errors));
    }

    private function entryRegistration(StateFestEvent $event, $entry): StateFestRegistration
    {
        $registration = new StateFestRegistration([
            'state_event_id' => $event->id, 'item_id' => $entry->item_id,
            'school_id' => $entry->school_id,
            'sahodaya_id' => $entry->intake?->sahodaya_id ?: $entry->intake?->source_tenant_id,
        ]);
        $registration->id = -1 * (int) $entry->id;
        $roster = collect($entry->meta['participants'] ?? [])->filter(fn ($member) => is_array($member));
        if ($roster->isEmpty()) {
            $roster = collect([array_merge($entry->meta ?? [], [
                'student_name' => $entry->student_name, 'roll_number' => $entry->roll_number,
                'class_name' => $entry->class_name,
            ])]);
        }
        $registration->setRelation('participants', $roster->map(fn ($member) => new StateFestParticipant([
            'student_name' => $member['student_name'] ?? $entry->student_name,
            'roll_number' => $member['roll_number'] ?? null,
            'class_name' => $member['class_name'] ?? null,
            'is_standby' => (bool) ($member['is_standby'] ?? false),
            'withdrawn_at' => $member['withdrawn_at'] ?? null,
            'meta' => $member,
        ])));

        return $registration;
    }

    private function genderProblems(FestStateProgramItem $item, Collection $participants): array
    {
        $normalize = fn ($value) => match (strtolower(trim((string) $value))) {
            'male', 'boy', 'boys', 'm' => 'male',
            'female', 'girl', 'girls', 'f' => 'female',
            default => null,
        };
        $required = $normalize($item->gender);
        if (! $required) {
            return [];
        }

        return $participants->map(function ($participant) use ($normalize, $required) {
            $actual = $normalize($participant->meta['gender'] ?? null);
            if ($actual === $required) {
                return null;
            }

            return ['code' => $actual ? self::WRONG_GENDER : self::UNKNOWN_GENDER,
                'message' => $actual
                    ? "{$participant->student_name} does not meet this item's {$required} eligibility."
                    : "{$participant->student_name}'s gender is not recorded; eligibility cannot be verified."];
        })->filter()->values()->all();
    }

    private function identity(StateFestRegistration $registration, StateFestParticipant $participant): ?string
    {
        $id = $participant->meta['source_student_id'] ?? $participant->roll_number;
        if (blank($id) || blank($registration->school_id)) {
            return null;
        }

        return json_encode([$registration->sahodaya_id, $registration->school_id,
            filled($participant->meta['source_student_id'] ?? null) ? 'student' : 'roll', (string) $id]);
    }

    private function participationProblems(StateFestEvent $event, StateFestRegistration $registration, Collection $participants, ?Collection $pool): array
    {
        $limits = $event->settings ?? [];
        $pool ??= StateFestRegistration::where('state_event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])->with('participants')->get();
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)->get()->keyBy('id');
        $problems = [];
        foreach ($participants as $participant) {
            $identity = $this->identity($registration, $participant);
            if ($identity === null) {
                if (array_filter(array_intersect_key($limits, array_flip(['max_items_per_student', 'max_individual_items_per_student', 'max_group_items_per_student'])))) {
                    $problems[] = ['code' => self::UNKNOWN_IDENTITY, 'message' => "{$participant->student_name} needs a student ID or roll number to verify participation limits."];
                }

                continue;
            }
            $matches = $pool->filter(fn ($other) => $other->id !== $registration->id
                && $other->participants->contains(fn ($member) => $member->isCompeting()
                    && $this->identity($other, $member) === $identity));
            if ($matches->contains(fn ($other) => $other->item_id === $registration->item_id)) {
                $problems[] = ['code' => self::DUPLICATE_ITEM, 'message' => "{$participant->student_name} is already entered for this item."];
            }
            $counts = ['max_items_per_student' => 1, 'max_individual_items_per_student' => 0, 'max_group_items_per_student' => 0];
            foreach ($matches->concat([$registration]) as $other) {
                $group = FestTeamSquadRules::isMultiPerson($items->get($other->item_id)?->participant_type);
                $counts[$group ? 'max_group_items_per_student' : 'max_individual_items_per_student']++;
            }
            $counts['max_items_per_student'] = $matches->count() + 1;
            foreach ($counts as $key => $count) {
                if (isset($limits[$key]) && $count > (int) $limits[$key]) {
                    $problems[] = ['code' => self::STUDENT_LIMIT,
                        'message' => "{$participant->student_name} has {$count} entries; {$key} allows {$limits[$key]}."];
                }
            }
        }

        return $problems;
    }

    /** @param  Collection<int, StateFestParticipant>  $competing */
    private function categoryProblems(StateFestEvent $event, FestStateProgramItem $item, Collection $competing): array
    {
        if (! filled($item->class_group)) {
            return [];
        }

        $category = StateClassCategory::where('state_event_id', $event->id)
            ->where('code', $item->class_group)->first();

        if (! $category) {
            return [[
                'code' => self::NO_CATEGORY,
                'message' => "This item is in \"{$item->class_group}\", which is not a category on this event, so nothing checks its entries. Seed the categories on the Categories & Eligibility tab.",
            ]];
        }

        // An open category is the group-item case: members' classes vary by design.
        if ($category->is_open) {
            return [];
        }

        $problems = [];

        foreach ($competing as $participant) {
            $classNumber = FestStudentClassResolver::classNumberFromName($participant->class_name);

            if ($classNumber === null) {
                $problems[] = [
                    'code' => self::UNKNOWN_CLASS,
                    'message' => "{$participant->student_name}'s class is recorded as \""
                        .($participant->class_name ?: 'blank').'", which cannot be read as a class number, so their category cannot be checked.',
                ];

                continue;
            }

            if (! $category->admits($classNumber)) {
                $problems[] = [
                    'code' => self::WRONG_CATEGORY,
                    'message' => "{$participant->student_name} is in Class {$classNumber}, outside {$category->label} ({$category->range()}).",
                ];
            }
        }

        return $problems;
    }

    /** @param  Collection<int, StateFestParticipant>  $competing */
    private function teamSizeProblems(FestStateProgramItem $item, Collection $competing): array
    {
        $size = $competing->count();
        $fixedSize = match ($item->participant_type) {
            'individual' => 1,
            'pair' => 2,
            'trio' => 3,
            default => null,
        };
        $min = (int) ($item->min_group_size ?: ($fixedSize ?? 1));
        $max = (int) ($item->max_group_size ?: ($fixedSize ?? 0));

        if ($min && $size < $min) {
            return [[
                'code' => self::TEAM_SIZE,
                'message' => "This entry has {$size} competing member(s); {$item->item_code} needs at least {$min}.",
            ]];
        }

        if ($max && $size > $max) {
            return [[
                'code' => self::TEAM_SIZE,
                'message' => "This entry has {$size} competing member(s); {$item->item_code} allows at most {$max}.",
            ]];
        }

        return [];
    }
}
