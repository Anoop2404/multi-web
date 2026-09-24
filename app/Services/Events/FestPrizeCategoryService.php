<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestPrizeCategory;
use App\Models\FestPrizeCategoryItem;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prize categories and the champions they crown, at Sahodaya level.
 *
 * The counterpart of State\StatePrizeCategoryService, and deliberately the same shape so a trophy
 * means the same thing at both levels. Two differences are real rather than cosmetic:
 *
 * - A Sahodaya event competes School against School, so there is no Sahodaya title here.
 * - `fest_marks` stores no points column, so a mark's worth is computed through
 *   FestGradePointService — the same path the school championship uses, so a trophy and the main
 *   standings can never disagree about what a placing was worth.
 */
class FestPrizeCategoryService
{
    public function __construct(private FestGradePointService $gradePoints) {}

    /** @return Collection<int, FestPrizeCategory> */
    public function categories(FestEvent $event, bool $activeOnly = false): Collection
    {
        return FestPrizeCategory::where('event_id', $event->id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->with('items')->get();
    }

    /** @param  array<string, mixed>  $data */
    public function save(FestEvent $event, array $data, ?int $id = null): FestPrizeCategory
    {
        $awards = array_values(array_intersect($data['awards'] ?? [], array_keys(FestPrizeCategory::AWARDS)));

        if ($awards === []) {
            throw ValidationException::withMessages([
                'awards' => 'Choose at least one title for this category to award, or it crowns nobody.',
            ]);
        }

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'awards' => $awards,
            'is_overall' => (bool) ($data['is_overall'] ?? false),
            'honour_count' => (int) ($data['honour_count'] ?? 3),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($id) {
            $category = FestPrizeCategory::where('event_id', $event->id)->findOrFail($id);
            $category->forceFill($attributes)->save();

            return $category->fresh('items');
        }

        $code = filled($data['code'] ?? null) ? Str::slug($data['code']) : Str::slug($data['name']);

        if (FestPrizeCategory::where('event_id', $event->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => "There is already a category coded \"{$code}\" on this event.",
            ]);
        }

        return FestPrizeCategory::create(['event_id' => $event->id, 'code' => $code] + $attributes);
    }

    public function delete(FestEvent $event, int $id): void
    {
        $category = FestPrizeCategory::where('event_id', $event->id)->findOrFail($id);

        // Children removed explicitly rather than left to the FK cascade: cascades are not enforced
        // on every connection this runs against, and an orphaned assignment row would quietly rejoin
        // a later category that reused the id.
        DB::transaction(function () use ($category) {
            FestPrizeCategoryItem::where('prize_category_id', $category->id)->delete();
            $category->delete();
        });
    }

    /**
     * @param  list<int>  $itemIds
     * @return int items assigned
     */
    public function assignItems(FestEvent $event, int $id, array $itemIds): int
    {
        $category = FestPrizeCategory::where('event_id', $event->id)->findOrFail($id);

        if ($category->is_overall) {
            throw ValidationException::withMessages([
                'items' => 'An overall category already covers every item, so it takes no assignments. Turn off "overall" first if you want to choose items.',
            ]);
        }

        $valid = FestEventItem::where('event_id', $event->id)->whereIn('id', $itemIds)->pluck('id');

        return DB::transaction(function () use ($category, $valid) {
            FestPrizeCategoryItem::where('prize_category_id', $category->id)->delete();

            foreach ($valid as $itemId) {
                FestPrizeCategoryItem::create(['prize_category_id' => $category->id, 'item_id' => $itemId]);
            }

            return $valid->count();
        });
    }

    /** @return Collection<int, int> */
    public function itemIdsFor(FestEvent $event, FestPrizeCategory $category): Collection
    {
        if ($category->is_overall) {
            return FestEventItem::where('event_id', $event->id)->pluck('id');
        }

        return $category->items->pluck('item_id');
    }

    /**
     * Who wins a category.
     *
     * Published items only, read from the item's own `results_published_at` — the same flag the public
     * results page honours, so a trophy cannot name a winner the public cannot yet see.
     *
     * @return array<string, mixed>
     */
    public function standings(FestEvent $event, FestPrizeCategory $category): array
    {
        $itemIds = $this->itemIdsFor($event, $category);

        $countable = FestEventItem::where('event_id', $event->id)
            ->whereIn('id', $itemIds)
            ->when(! $event->results_published, fn ($q) => $q->whereNotNull('results_published_at'))
            ->pluck('id');

        $marks = $countable->isEmpty() ? collect() : FestMark::where('event_id', $event->id)
            ->whereIn('item_id', $countable)
            ->whereNotNull('position')
            ->with(['participant.registration', 'participant.student', 'item'])
            ->get()
            ->filter(fn (FestMark $m) => $m->participant && $m->participant->registration);

        $schoolNames = Tenant::whereIn('id', $marks->pluck('participant.registration.school_id')->filter()->unique())
            ->pluck('name', 'id');

        return [
            'category' => [
                'id' => $category->id, 'code' => $category->code, 'name' => $category->name,
                'description' => $category->description, 'is_overall' => $category->is_overall,
                'awards' => $category->awards, 'award_labels' => $category->awardLabels(),
                'honour_count' => $category->honour_count,
            ],
            'items' => $itemIds->count(),
            'items_counted' => $countable->count(),
            'is_complete' => $itemIds->count() > 0 && $countable->count() === $itemIds->count(),
            'individual' => $category->awards(FestPrizeCategory::AWARD_INDIVIDUAL)
                ? $this->individualStanding($event, $marks, $schoolNames, $category->honour_count) : null,
            'school' => $category->awards(FestPrizeCategory::AWARD_SCHOOL)
                ? $this->schoolStanding($event, $marks, $schoolNames, $category->honour_count) : null,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function allStandings(FestEvent $event): Collection
    {
        return $this->categories($event, activeOnly: true)
            ->map(fn (FestPrizeCategory $c) => $this->standings($event, $c));
    }

    /**
     * Points for a mark, through the event's own grade/point rules.
     *
     * Cached per mark id because pointsForMark() resolves the grade from the score and consults the
     * event's rules, and a trophy over forty items asks the same question of the same mark once per
     * category it appears in.
     */
    private array $pointsCache = [];

    private function pointsFor(FestEvent $event, FestMark $mark): int
    {
        return $this->pointsCache[$mark->id] ??= (int) $this->gradePoints->pointsForMark($event, $mark);
    }

    /** @return list<array<string, mixed>> */
    private function individualStanding(FestEvent $event, Collection $marks, Collection $schoolNames, int $limit): array
    {
        return $this->ranked(
            $marks->groupBy(fn (FestMark $m) => $m->participant->student_id ?? "p{$m->participant_id}")
                ->map(function (Collection $group) use ($event, $schoolNames) {
                    $participant = $group->first()->participant;

                    return [
                        'name' => $participant->student?->name ?? 'Participant',
                        'class_name' => $participant->student?->class_name,
                        'school' => $schoolNames[$participant->registration->school_id] ?? null,
                        'points' => $group->sum(fn (FestMark $m) => $this->pointsFor($event, $m)),
                        'firsts' => $group->where('position', 1)->count(),
                        'items' => $group->count(),
                    ];
                }),
            $limit,
        );
    }

    /** @return list<array<string, mixed>> */
    private function schoolStanding(FestEvent $event, Collection $marks, Collection $schoolNames, int $limit): array
    {
        return $this->ranked(
            $marks->groupBy(fn (FestMark $m) => $m->participant->registration->school_id)
                ->map(fn (Collection $group, $schoolId) => [
                    'name' => $schoolNames[$schoolId] ?? 'Unattributed',
                    'school_id' => $schoolId,
                    'points' => $group->sum(fn (FestMark $m) => $this->pointsFor($event, $m)),
                    'firsts' => $group->where('position', 1)->count(),
                    'items' => $group->count(),
                ]),
            $limit,
        );
    }

    /**
     * Rank by points then firsts, sharing a rank on a true tie.
     *
     * Identical to the State side's rule on purpose: joint champions are joint, and honour_count
     * limits places rather than rows so three joint firsts are all listed.
     *
     * @param  Collection<int|string, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function ranked(Collection $rows, int $limit): array
    {
        $sorted = $rows->filter(fn (array $r) => $r['points'] > 0)
            ->sortByDesc(fn (array $r) => [$r['points'], $r['firsts']])
            ->values();

        $ranked = [];
        $rank = 0;
        $seen = 0;
        $previous = null;

        foreach ($sorted as $row) {
            $seen++;
            $key = [$row['points'], $row['firsts']];

            if ($previous === null || $key !== $previous) {
                $rank = $seen;
            }

            $previous = $key;

            if ($limit > 0 && $rank > $limit) {
                break;
            }

            $ranked[] = $row + ['rank' => $rank];
        }

        $counts = collect($ranked)->countBy('rank');

        return collect($ranked)->map(fn (array $r) => ['is_tied' => $counts[$r['rank']] > 1] + $r)->values()->all();
    }
}
