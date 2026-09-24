<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateConductAudit;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemResult;
use App\Models\State\StateSahodaya;
use App\Services\State\StateGradePointService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 7 of the State Kalotsav module — results, points and standings.
 *
 * The defining difference from the Sahodaya module is here: points aggregate to the **Sahodaya**,
 * which is the competing organization at State level, while the School each participant came from
 * is carried through so a Sahodaya's total can be broken down by the Schools that earned it. The
 * School is never the competitor and never a row in the ranking.
 *
 * Standings group on the canonical Sahodaya identity from Phase 1, so a Sahodaya promoted mid-season
 * appears once with one total rather than twice with half each.
 */
class StateResultService
{
    public function __construct(private StateGradePointService $grades) {}

    /**
     * Rank an item from its aggregated marks.
     *
     * Equal scores share a position and the next position is skipped — two firsts are followed by a
     * third, not a second. That is how a Kalotsavam reads a result sheet, and it is also why ties
     * are counted: a tie for first is a decision the office has to make before publishing.
     *
     * @return array{ranked: int, ties: int}
     */
    public function computeItem(StateFestEvent $event, FestStateProgramItem $item): array
    {
        $result = $this->resultRow($event, $item);

        if ($result->isLocked()) {
            throw ValidationException::withMessages([
                'item' => 'This item\'s result is locked. Unlock it before recomputing.',
            ]);
        }

        $ranking = $this->ranking($event, $item);
        $isGroup = $this->isGroupItem($item);

        DB::connection('state')->transaction(function () use ($ranking, $event, $item, $isGroup) {
            foreach ($ranking as $row) {
                $mark = $row['mark'];

                // A mark with a score but no grade would score zero points, silently — and marks
                // arrive that way from the qualifier projection and from direct entry, not only from
                // aggregation. The grade is derived from the score and kept, so the stored mark says
                // what it was worth and why.
                $grade = filled($mark->grade)
                    ? $mark->grade
                    : $this->grades->resolveGradeFromScore($event, (float) $mark->score, $item->id);

                $mark->forceFill([
                    'grade' => $grade,
                    'position' => $row['position'],
                    'points' => $this->grades->pointsForGradePosition($event, $grade, $row['position'], $isGroup),
                    'status' => 'ranked',
                ])->save();
            }
        });

        $ties = $ranking->where('tied', true)->count();

        $result->forceFill([
            'status' => $result->status === StateItemResult::PUBLISHED ? StateItemResult::PUBLISHED : StateItemResult::PROVISIONAL,
            'computed_at' => now(),
            'ranked_count' => $ranking->count(),
            'tie_count' => $ties,
        ])->save();

        return ['ranked' => $ranking->count(), 'ties' => $ties];
    }

    /**
     * The ranking an item's marks imply, computed and returned without being written.
     *
     * Split out of computeItem() so the same rule can be checked against what is already stored
     * without changing it — Phase 11's pre-cutover verification needs to compare, not recompute, and
     * a second implementation of the tie rule would drift from this one within a season.
     *
     * @return Collection<int, array{mark: StateFestMark, participant_id: int, position: int, tied: bool}>
     */
    public function ranking(StateFestEvent $event, FestStateProgramItem $item): Collection
    {
        $marks = StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', StateFestRegistration::where('state_event_id', $event->id)
                ->where('item_id', $item->id)->select('id'))
            ->get()
            ->sortByDesc(fn (StateFestMark $m) => (float) $m->score)
            ->values();

        $position = 0;
        $seen = 0;
        $previousScore = null;
        $rows = collect();

        foreach ($marks as $mark) {
            $seen++;
            $score = (float) $mark->score;
            $tied = $previousScore !== null && $score >= $previousScore;

            if (! $tied) {
                // Skipped positions after a tie: two firsts are followed by a third.
                $position = $seen;
            }

            $previousScore = $score;

            $rows->push([
                'mark' => $mark,
                'participant_id' => (int) $mark->participant_id,
                'position' => $position,
                'tied' => $tied,
            ]);
        }

        return $rows;
    }

    private function isGroupItem(FestStateProgramItem $item): bool
    {
        return in_array($item->participant_type, ['group', 'team', 'pair', 'trio'], true);
    }

    public function publishItem(StateFestEvent $event, FestStateProgramItem $item, array $context = []): StateItemResult
    {
        $result = $this->resultRow($event, $item);

        if ($result->computed_at === null) {
            throw ValidationException::withMessages(['item' => 'Compute the result before publishing it.']);
        }

        $previous = $result->status;
        $result->forceFill(['status' => StateItemResult::PUBLISHED, 'published_at' => now()])->save();

        $this->auditResult($event, $item, $previous, StateItemResult::PUBLISHED, $context);

        return $result->fresh();
    }

    /** Withdraw a published result — recorded, because someone saw it. */
    public function unpublishItem(StateFestEvent $event, FestStateProgramItem $item, array $context = []): StateItemResult
    {
        $result = $this->resultRow($event, $item);

        if ($result->isLocked()) {
            throw ValidationException::withMessages(['item' => 'This result is locked.']);
        }

        if (blank($context['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => 'Say why a published result is being withdrawn — it has already been seen.',
            ]);
        }

        $previous = $result->status;
        $result->forceFill(['status' => StateItemResult::PROVISIONAL, 'published_at' => null])->save();

        $this->auditResult($event, $item, $previous, StateItemResult::PROVISIONAL, $context);

        return $result->fresh();
    }

    public function lockItem(StateFestEvent $event, FestStateProgramItem $item, array $context = []): StateItemResult
    {
        $result = $this->resultRow($event, $item);

        if (! $result->isPublic()) {
            throw ValidationException::withMessages(['item' => 'Publish the result before locking it.']);
        }

        $previous = $result->status;
        $result->forceFill(['status' => StateItemResult::LOCKED, 'locked_at' => now()])->save();

        $this->auditResult($event, $item, $previous, StateItemResult::LOCKED, $context);

        return $result->fresh();
    }

    /**
     * Sahodaya standings — the State's primary leaderboard.
     *
     * Only published items count. A provisional result is the office's working view, and letting it
     * move the public table would mean the standings change under a Sahodaya without anything having
     * been announced.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sahodayaStandings(StateFestEvent $event, bool $includeProvisional = false): Collection
    {
        $items = $this->countableItemIds($event, $includeProvisional);

        if ($items->isEmpty()) {
            return collect();
        }

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->whereIn('item_id', $items)->get()->keyBy('id');

        $marks = StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')->get();

        $directory = StateSahodaya::whereIn('id', $registrations->pluck('sahodaya_id')->filter()->unique())
            ->get()->keyBy('id');

        return $marks->groupBy(fn (StateFestMark $m) => $registrations[$m->registration_id]->sahodaya_id ?? 'unattributed')
            ->map(function (Collection $group, string $sahodayaId) use ($registrations, $directory) {
                $first = $registrations[$group->first()->registration_id] ?? null;

                return [
                    'sahodaya_id' => $sahodayaId,
                    'sahodaya' => $directory->get($sahodayaId)?->name ?? $first?->sahodaya_name ?? 'Unattributed',
                    'district' => $directory->get($sahodayaId)?->district,
                    'origin' => $directory->get($sahodayaId)?->origin,
                    'points' => (int) $group->sum('points'),
                    'firsts' => $group->where('position', 1)->count(),
                    'seconds' => $group->where('position', 2)->count(),
                    'thirds' => $group->where('position', 3)->count(),
                    'entries' => $group->count(),
                    'items' => $group->map(fn ($m) => $registrations[$m->registration_id]->item_id ?? null)->filter()->unique()->count(),
                ];
            })
            // Points first, then firsts, then seconds — the conventional tie-break, and stated here
            // rather than left to whatever order the database returns.
            ->sortByDesc(fn (array $r) => [$r['points'], $r['firsts'], $r['seconds'], $r['thirds']])
            ->values()
            ->map(function (array $row, int $i) {
                $row['rank'] = $i + 1;

                return $row;
            });
    }

    /**
     * Which Schools earned a Sahodaya's points. The drill-down behind the standings — the School is
     * never a competitor, but a Sahodaya's total is made of its Schools' work.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function schoolContribution(StateFestEvent $event, string $sahodayaId, bool $includeProvisional = false): Collection
    {
        $items = $this->countableItemIds($event, $includeProvisional);

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('sahodaya_id', $sahodayaId)
            ->whereIn('item_id', $items)->get()->keyBy('id');

        return StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')->get()
            ->groupBy(fn (StateFestMark $m) => $registrations[$m->registration_id]->school_name
                ?: ($registrations[$m->registration_id]->school_id ?: 'Unattributed'))
            ->map(fn (Collection $group, string $school) => [
                'school' => $school,
                'points' => (int) $group->sum('points'),
                'firsts' => $group->where('position', 1)->count(),
                'entries' => $group->count(),
            ])
            ->sortByDesc('points')->values();
    }

    /**
     * Individual championship: the highest-scoring participants, with the School and Sahodaya each
     * competed for.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function individualChampionship(StateFestEvent $event, bool $includeProvisional = false): Collection
    {
        $items = $this->countableItemIds($event, $includeProvisional);

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->whereIn('item_id', $items)->with('participants')->get()->keyBy('id');

        $participants = $registrations->flatMap(fn ($r) => $r->participants)->keyBy('id');

        return StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')->get()
            ->groupBy('participant_id')
            ->map(function (Collection $group, $participantId) use ($registrations, $participants) {
                $registration = $registrations[$group->first()->registration_id] ?? null;

                return [
                    'participant' => $participants->get($participantId)?->student_name ?? '—',
                    'class_name' => $participants->get($participantId)?->class_name,
                    'sahodaya' => $registration?->sahodaya_name,
                    'school' => $registration?->school_name ?: $registration?->school_id,
                    'points' => (int) $group->sum('points'),
                    'firsts' => $group->where('position', 1)->count(),
                    'items' => $group->count(),
                ];
            })
            ->filter(fn (array $r) => $r['points'] > 0)
            ->sortByDesc(fn (array $r) => [$r['points'], $r['firsts']])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function itemResults(StateFestEvent $event): Collection
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->orderBy('display_order')->orderBy('title')->get();

        $results = StateItemResult::where('state_event_id', $event->id)->get()->keyBy('item_id');

        // Qualified: both tables carry state_event_id, so an unqualified where is ambiguous.
        $markCounts = StateFestMark::where('state_fest_marks.state_event_id', $event->id)
            ->join('state_fest_registrations', 'state_fest_registrations.id', '=', 'state_fest_marks.registration_id')
            ->selectRaw('state_fest_registrations.item_id as item_id, count(*) as c')
            ->groupBy('state_fest_registrations.item_id')->pluck('c', 'item_id');

        return $items->map(function (FestStateProgramItem $item) use ($results, $markCounts) {
            $result = $results->get($item->id);

            return [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'title' => $item->title,
                'marks' => (int) ($markCounts[$item->id] ?? 0),
                'status' => $result?->status ?? StateItemResult::DRAFT,
                'ranked' => (int) ($result?->ranked_count ?? 0),
                'ties' => (int) ($result?->tie_count ?? 0),
                'computed_at' => $result?->computed_at?->toDateTimeString(),
                'published_at' => $result?->published_at?->toDateTimeString(),
                'is_locked' => (bool) $result?->isLocked(),
            ];
        });
    }

    /** Item ids whose results count toward standings. */
    private function countableItemIds(StateFestEvent $event, bool $includeProvisional): Collection
    {
        $statuses = $includeProvisional
            ? [StateItemResult::PROVISIONAL, StateItemResult::PUBLISHED, StateItemResult::LOCKED]
            : [StateItemResult::PUBLISHED, StateItemResult::LOCKED];

        return StateItemResult::where('state_event_id', $event->id)
            ->whereIn('status', $statuses)->pluck('item_id');
    }

    private function resultRow(StateFestEvent $event, FestStateProgramItem $item): StateItemResult
    {
        return StateItemResult::firstOrCreate(
            ['state_event_id' => $event->id, 'item_id' => $item->id],
            [
                'id' => (string) Str::uuid(),
                'state_id' => $event->state_id,
                'item_code' => $item->item_code,
                'status' => StateItemResult::DRAFT,
            ],
        );
    }

    private function auditResult(StateFestEvent $event, FestStateProgramItem $item, string $from, string $to, array $context): void
    {
        StateConductAudit::create([
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'kind' => 'result',
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'value_from' => $from,
            'value_to' => $to,
            'reason' => $context['reason'] ?? null,
            'changed_by_user_id' => $context['user_id'] ?? null,
            'changed_by_name' => $context['user_name'] ?? null,
            'created_at' => now(),
        ]);
    }
}
