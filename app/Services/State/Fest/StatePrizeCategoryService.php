<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StatePrizeCategory;
use App\Models\State\StatePrizeCategoryItem;
use App\Models\State\StateSahodaya;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prize categories and the champions they crown.
 *
 * A category is a trophy: named by the State office, given a set of items, and awarding any of an
 * individual, a school or a Sahodaya title. An "overall" category covers every item and so needs no
 * assignments — it is the common championship, kept as a category rather than a special case so it
 * appears in the same list and prints on the same sheet.
 *
 * Only **published or locked** item results count. A trophy computed from provisional marks would
 * change under the winner's feet between the announcement and the certificate.
 */
class StatePrizeCategoryService
{
    /** @return Collection<int, StatePrizeCategory> */
    public function categories(StateFestEvent $event, bool $activeOnly = false): Collection
    {
        return StatePrizeCategory::where('state_event_id', $event->id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->with('items')->get();
    }

    /** @param  array<string, mixed>  $data */
    public function save(StateFestEvent $event, array $data, ?string $id = null): StatePrizeCategory
    {
        $awards = array_values(array_intersect(
            $data['awards'] ?? [],
            array_keys(StatePrizeCategory::AWARDS),
        ));

        if ($awards === []) {
            throw ValidationException::withMessages([
                'awards' => 'Choose at least one title for this category to award, or it crowns nobody.',
            ]);
        }

        $attributes = [
            'state_id' => $event->state_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'awards' => $awards,
            'is_overall' => (bool) ($data['is_overall'] ?? false),
            'honour_count' => (int) ($data['honour_count'] ?? 3),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($id) {
            $category = StatePrizeCategory::where('state_event_id', $event->id)->findOrFail($id);
            $category->forceFill($attributes)->save();

            return $category->fresh('items');
        }

        $code = filled($data['code'] ?? null) ? Str::slug($data['code']) : Str::slug($data['name']);

        if (StatePrizeCategory::where('state_event_id', $event->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => "There is already a category coded \"{$code}\" on this event.",
            ]);
        }

        return StatePrizeCategory::create(['state_event_id' => $event->id, 'code' => $code] + $attributes);
    }

    public function delete(StateFestEvent $event, string $id): void
    {
        $category = StatePrizeCategory::where('state_event_id', $event->id)->findOrFail($id);

        DB::connection('state')->transaction(function () use ($category) {
            $category->items()->delete();
            $category->delete();
        });
    }

    /**
     * Replace a category's items.
     *
     * @param  list<string>  $itemIds
     * @return int items assigned
     */
    public function assignItems(StateFestEvent $event, string $id, array $itemIds): int
    {
        $category = StatePrizeCategory::where('state_event_id', $event->id)->findOrFail($id);

        if ($category->is_overall) {
            throw ValidationException::withMessages([
                'items' => 'An overall category already covers every item, so it takes no assignments. Turn off "overall" first if you want to choose items.',
            ]);
        }

        // Only items of this event's own program: assigning someone else's item would put a foreign
        // result into this trophy.
        $valid = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->whereIn('id', $itemIds)->pluck('id');

        return DB::connection('state')->transaction(function () use ($category, $valid) {
            StatePrizeCategoryItem::where('prize_category_id', $category->id)->delete();

            foreach ($valid as $itemId) {
                StatePrizeCategoryItem::create([
                    'prize_category_id' => $category->id,
                    'item_id' => $itemId,
                ]);
            }

            return $valid->count();
        });
    }

    /**
     * The items a category counts, which for an overall category is every item in the program.
     *
     * @return Collection<int, string>
     */
    public function itemIdsFor(StateFestEvent $event, StatePrizeCategory $category): Collection
    {
        if ($category->is_overall) {
            return FestStateProgramItem::where('state_program_id', $event->state_program_id)->pluck('id');
        }

        return $category->items->pluck('item_id');
    }

    /**
     * Who wins a category.
     *
     * @return array<string, mixed>
     */
    public function standings(StateFestEvent $event, StatePrizeCategory $category): array
    {
        $itemIds = $this->itemIdsFor($event, $category);

        // Published or locked only — see the class docblock.
        $countable = StateItemResult::where('state_event_id', $event->id)
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', [StateItemResult::PUBLISHED, StateItemResult::LOCKED])
            ->pluck('item_id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->whereIn('item_id', $countable)
            ->with('participants')->get()->keyBy('id');

        $marks = $registrations->isEmpty() ? collect() : StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')->get();

        $directory = StateSahodaya::whereIn('id', $registrations->pluck('sahodaya_id')->filter()->unique())
            ->get()->keyBy('id');

        return [
            'category' => [
                'id' => $category->id, 'code' => $category->code, 'name' => $category->name,
                'description' => $category->description, 'is_overall' => $category->is_overall,
                'awards' => $category->awards, 'award_labels' => $category->awardLabels(),
                'honour_count' => $category->honour_count,
            ],
            'items' => $itemIds->count(),
            'items_counted' => $countable->count(),
            // Said plainly: a trophy whose items are not all published is a provisional trophy, and
            // the operator should know before it is announced.
            'is_complete' => $itemIds->count() > 0 && $countable->count() === $itemIds->count(),
            'individual' => $category->awards(StatePrizeCategory::AWARD_INDIVIDUAL)
                ? $this->individualStanding($marks, $registrations, $category->honour_count) : null,
            'school' => $category->awards(StatePrizeCategory::AWARD_SCHOOL)
                ? $this->schoolStanding($marks, $registrations, $category->honour_count) : null,
            'sahodaya' => $category->awards(StatePrizeCategory::AWARD_SAHODAYA)
                ? $this->sahodayaStanding($marks, $registrations, $directory, $category->honour_count) : null,
        ];
    }

    /** Every active category's standings, for the page and for the report. */
    public function allStandings(StateFestEvent $event): Collection
    {
        return $this->categories($event, activeOnly: true)
            ->map(fn (StatePrizeCategory $c) => $this->standings($event, $c));
    }

    /**
     * @param  Collection<int, StateFestMark>  $marks
     * @param  Collection<int, StateFestRegistration>  $registrations
     * @return list<array<string, mixed>>
     */
    private function individualStanding(Collection $marks, Collection $registrations, int $limit): array
    {
        $participants = $registrations->flatMap(fn ($r) => $r->participants)->keyBy('id');

        return $this->ranked(
            $marks->groupBy('participant_id')
                ->map(function (Collection $group, $participantId) use ($registrations, $participants) {
                    $registration = $registrations[$group->first()->registration_id] ?? null;

                    return [
                        'name' => $participants->get($participantId)?->student_name ?? '—',
                        'class_name' => $participants->get($participantId)?->class_name,
                        // Both names, because the State competes Sahodaya while the person is from a
                        // school, and a trophy list naming only one is wrong to somebody.
                        'school' => $registration?->school_name ?: $registration?->school_id,
                        'sahodaya' => $registration?->sahodaya_name,
                        'points' => (int) $group->sum('points'),
                        'firsts' => $group->where('position', 1)->count(),
                        'items' => $group->count(),
                    ];
                }),
            $limit,
        );
    }

    /** @return list<array<string, mixed>> */
    private function schoolStanding(Collection $marks, Collection $registrations, int $limit): array
    {
        return $this->ranked(
            $marks->groupBy(fn (StateFestMark $m) => $registrations[$m->registration_id]->school_name
                ?: ($registrations[$m->registration_id]->school_id ?: 'Unattributed'))
                ->map(fn (Collection $group, string $school) => [
                    'name' => $school,
                    'sahodaya' => $registrations[$group->first()->registration_id]->sahodaya_name,
                    'points' => (int) $group->sum('points'),
                    'firsts' => $group->where('position', 1)->count(),
                    'items' => $group->count(),
                ]),
            $limit,
        );
    }

    /** @return list<array<string, mixed>> */
    private function sahodayaStanding(Collection $marks, Collection $registrations, Collection $directory, int $limit): array
    {
        return $this->ranked(
            $marks->groupBy(fn (StateFestMark $m) => $registrations[$m->registration_id]->sahodaya_id ?? 'unattributed')
                ->map(fn (Collection $group, string $sahodayaId) => [
                    'name' => $directory->get($sahodayaId)?->name
                        ?? $registrations[$group->first()->registration_id]->sahodaya_name
                        ?? 'Unattributed',
                    'district' => $directory->get($sahodayaId)?->district,
                    'sahodaya_id' => $sahodayaId,
                    'points' => (int) $group->sum('points'),
                    'firsts' => $group->where('position', 1)->count(),
                    'items' => $group->count(),
                ]),
            $limit,
        );
    }

    /**
     * Rank by points, then firsts, and share a rank on a true tie.
     *
     * Shared rather than broken arbitrarily: two Sahodayas on the same points and the same number of
     * firsts are joint champions, and inventing a separation would award a trophy on row order.
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

            // Honour count is a number of *places*, not of rows: three joint firsts are three
            // champions, and cutting at three rows would drop one of them.
            if ($limit > 0 && $rank > $limit) {
                break;
            }

            $ranked[] = $row + ['rank' => $rank, 'is_tied' => false];
        }

        // Flagged after the fact so every member of a tie is marked, not just the second onwards.
        $counts = collect($ranked)->countBy('rank');

        return collect($ranked)
            ->map(fn (array $r) => ['is_tied' => $counts[$r['rank']] > 1] + $r)
            ->values()->all();
    }
}
