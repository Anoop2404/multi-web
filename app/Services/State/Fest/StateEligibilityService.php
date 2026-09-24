<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateClassCategory;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Support\FestStudentClassResolver;
use Illuminate\Support\Collection;

/**
 * Whether a participant may compete in the item they are entered for.
 *
 * Two rules, both from the item itself: the class category and the team size. Each is reported
 * separately, because "wrong category" is a Sahodaya's mistake in selecting a winner, while "team too
 * small" is usually someone withdrawing after selection — the same word "ineligible" would hide the
 * difference.
 *
 * A class that cannot be read is reported as unknown, not as ineligible, and counted apart from real
 * violations. State entries arrive from Sahodayas as free text ("X B", "Std 10") and often with no
 * class at all, and refusing an entry because the platform could not parse a string would reject real
 * winners.
 *
 * **Gender is deliberately not checked.** Items carry male/female/open, but nothing in the qualifier
 * intake carries a participant's gender — not the entry, not its meta — so a check here would either
 * pass every row (dead code that reads as a working safeguard) or flag every row. Checking it needs
 * the intake to carry gender first.
 */
class StateEligibilityService
{
    public const OK = 'ok';

    public const WRONG_CATEGORY = 'wrong_category';

    public const TEAM_SIZE = 'team_size';

    public const UNKNOWN_CLASS = 'unknown_class';

    public const NO_CATEGORY = 'no_category';

    /**
     * Check one registration.
     *
     * @return array{status: string, problems: list<array{code: string, message: string}>}
     */
    public function check(StateFestEvent $event, StateFestRegistration $registration, ?FestStateProgramItem $item = null): array
    {
        $item ??= FestStateProgramItem::find($registration->item_id);
        $problems = [];

        if (! $item) {
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
        );

        return [
            'status' => $problems === [] ? self::OK : $problems[0]['code'],
            'problems' => $problems,
        ];
    }

    /**
     * Every approved entry with a problem. This is the screen a scrutiny officer works from before
     * finalising, so it reports rather than refuses — a State office decides what to do about a
     * Sahodaya that sent a Class 8 pupil for a Category 2 item, and that decision is not automatable.
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
                        .($participant->class_name ?: 'blank')."\", which cannot be read as a class number, so their category cannot be checked.",
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
        $min = (int) ($item->min_group_size ?: 0);
        $max = (int) ($item->max_group_size ?: 0);

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
