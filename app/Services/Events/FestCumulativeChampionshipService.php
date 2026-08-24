<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\FestPhaseScoreSnapshot;
use App\Models\FestResult;
use App\Models\FestScoreContribution;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FestCumulativeChampionshipService
{
    public function __construct(private FestGradePointService $gradePoints) {}

    public function consolidateAndLock(
        FestEvent $root,
        FestEventPhase $phase,
        ?int $actorId = null,
        ?string $reason = null,
    ): int {
        $root = $root->rootEvent();
        abort_unless($phase->event_id === $root->id && ! $phase->source_phase_id, 422, 'Lock a source phase belonging to this root event.');

        $leaves = $this->phaseLeaves($root, $phase);
        abort_if($leaves->isEmpty(), 422, 'Synchronize the operational events before locking phase points.');
        abort_if($leaves->contains(fn (FestEvent $leaf) => ! $leaf->results_published), 422, 'Publish every operational event in this phase before locking cumulative points.');

        return DB::transaction(function () use ($root, $phase, $leaves, $actorId, $reason) {
            $phase->newQuery()->whereKey($phase->id)->lockForUpdate()->first();
            $latestVersion = (int) FestPhaseScoreSnapshot::where('phase_id', $phase->id)->max('version');
            $latestValidVersion = (int) FestPhaseScoreSnapshot::where('phase_id', $phase->id)
                ->whereNull('invalidated_at')->max('version');
            $version = $latestVersion + 1;
            $opening = $this->openingMap($root, $phase);
            $eventRows = $leaves->flatMap(fn (FestEvent $leaf) => $this->eventContributionRows($root, $phase, $leaf));
            $current = $eventRows->groupBy(fn (array $row) => $this->mapKey($row['school_id'], $row['championship_category_key']))
                ->map(fn (Collection $rows) => (float) $rows->sum('points'));

            $snapshotRows = collect(array_unique(array_merge($opening->keys()->all(), $current->keys()->all())))
                ->map(function (string $key) use ($opening, $current) {
                    [$schoolId, $category] = explode('|', $key, 2);
                    $openingPoints = (float) ($opening[$key] ?? 0);
                    $currentPoints = (float) ($current[$key] ?? 0);

                    return [
                        'school_id' => $schoolId,
                        'championship_category_key' => $category,
                        'opening_points' => $openingPoints,
                        'current_points' => $currentPoints,
                        'closing_points' => $openingPoints + $currentPoints,
                    ];
                });

            if ($latestValidVersion > 0 && $this->sameAsLatest($phase, $latestValidVersion, $snapshotRows, $eventRows)) {
                return $latestValidVersion;
            }

            $now = now();
            foreach ($eventRows as $row) {
                FestScoreContribution::create($row + [
                    'root_event_id' => $root->id,
                    'phase_id' => $phase->id,
                    'version' => $version,
                ]);
            }

            $ranked = $this->applyRanks($snapshotRows);
            foreach ($ranked as $row) {
                FestPhaseScoreSnapshot::create($row + [
                    'root_event_id' => $root->id,
                    'phase_id' => $phase->id,
                    'version' => $version,
                    'locked_at' => $now,
                    'locked_by' => $actorId,
                    'correction_reason' => $reason,
                ]);
            }

            return $version;
        });
    }

    public function lockPublishedThrough(FestEvent $root, FestEventPhase $through, ?int $actorId = null): void
    {
        $root->rootEvent()->phases()
            ->where('results_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(fn (FestEventPhase $phase) => $this->consolidateAndLock(
                $root,
                $phase,
                $actorId,
                $phase->id === $through->id ? null : 'Recalculated after an earlier phase changed',
            ));
    }

    public function invalidateFrom(FestEvent $root, FestEventPhase $phase, ?int $actorId = null): void
    {
        $phaseIds = $root->rootEvent()->phases()
            ->where(function ($query) use ($phase) {
                $query->where('sort_order', '>', $phase->sort_order)
                    ->orWhere(fn ($same) => $same->where('sort_order', $phase->sort_order)->where('id', '>=', $phase->id));
            })->pluck('id');

        $attributes = ['invalidated_at' => now(), 'invalidated_by' => $actorId];
        FestPhaseScoreSnapshot::whereIn('phase_id', $phaseIds)->whereNull('invalidated_at')->update($attributes);
        FestScoreContribution::whereIn('phase_id', $phaseIds)->whereNull('invalidated_at')->update($attributes);
    }

    /** @return array{version:int, phase:FestEventPhase, rows:list<array<string, mixed>>}|null */
    public function publicStanding(FestEvent $event, ?string $category = null): ?array
    {
        if (! $event->parent_event_id || ! $event->source_phase_id) {
            return null;
        }

        $phase = FestEventPhase::find($event->source_phase_id);
        if (! $phase) {
            return null;
        }
        $category = $category
            ? $this->championshipCategoryKey($event->rootEvent(), $event, $category)
            : 'overall';
        $version = (int) FestPhaseScoreSnapshot::where('phase_id', $phase->id)
            ->whereNull('invalidated_at')->max('version');
        if ($version < 1) {
            return null;
        }

        $eventPoints = FestScoreContribution::where('phase_id', $phase->id)
            ->where('source_event_id', $event->id)
            ->where('version', $version)
            ->whereNull('invalidated_at')
            ->where('championship_category_key', $category)
            ->pluck('points', 'school_id');
        $schools = Tenant::whereIn('id', FestPhaseScoreSnapshot::where('phase_id', $phase->id)
            ->where('version', $version)->where('championship_category_key', $category)->pluck('school_id'))
            ->get(['id', 'name'])->keyBy('id');

        $rows = FestPhaseScoreSnapshot::where('phase_id', $phase->id)
            ->where('version', $version)
            ->whereNull('invalidated_at')
            ->where('championship_category_key', $category)
            ->orderBy('rank')
            ->get()
            ->map(fn (FestPhaseScoreSnapshot $row) => [
                'school_id' => $row->school_id,
                'school_name' => $schools[$row->school_id]?->name ?? $row->school_id,
                'opening_points' => (float) $row->opening_points,
                'event_points' => (float) ($eventPoints[$row->school_id] ?? 0),
                'phase_points' => (float) $row->current_points,
                'closing_points' => (float) $row->closing_points,
                'total_points' => (float) $row->closing_points,
                'rank' => $row->rank,
            ])->all();

        return ['version' => $version, 'phase' => $phase, 'rows' => $rows];
    }

    private function phaseLeaves(FestEvent $root, FestEventPhase $phase): Collection
    {
        return FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->orderBy('id')->get();
    }

    private function eventContributionRows(FestEvent $root, FestEventPhase $phase, FestEvent $event): Collection
    {
        $rows = FestResult::where('event_id', $event->id)->whereNull('item_id')->get()
            ->map(fn (FestResult $result) => [
                'source_event_id' => $event->id,
                'school_id' => $result->school_id,
                'source_category_key' => 'overall',
                'championship_category_key' => 'overall',
                'points' => (float) $result->total_points,
            ]);

        $column = $root->event_type === 'sports' ? 'age_group' : 'class_group';
        $marks = FestMark::where('event_id', $event->id)
            ->with(['item', 'participant.registration.item'])
            ->get()->unique(fn (FestMark $mark) => $mark->deduplicationKey());
        foreach ($marks as $mark) {
            $schoolId = $mark->participant?->registration?->school_id;
            $sourceCategory = (string) ($mark->item?->{$column} ?? 'open');
            $category = $this->championshipCategoryKey($root, $event, $sourceCategory);
            if (! $schoolId || $mark->participant?->disqualified_at) {
                continue;
            }
            $rows->push([
                'source_event_id' => $event->id,
                'school_id' => $schoolId,
                'source_category_key' => $sourceCategory,
                'championship_category_key' => $category,
                'points' => $this->gradePoints->pointsForMark($event, $mark),
            ]);
        }

        return $rows->groupBy(fn (array $row) => $this->mapKey($row['school_id'], $row['championship_category_key']))
            ->map(function (Collection $group) {
                $first = $group->first();
                $first['points'] = (float) $group->sum('points');

                return $first;
            })->values();
    }

    private function openingMap(FestEvent $root, FestEventPhase $phase): Collection
    {
        // FestEvent::phases() carries its own baked-in ->orderBy('sort_order') (ascending).
        // Chaining ->orderByDesc(...) on top of that does NOT replace it — Laravel appends
        // additional ORDER BY clauses, so the query actually ran as
        // "ORDER BY sort_order ASC, sort_order DESC, id DESC", where the first (ascending)
        // clause wins outright. For any event with 3+ phases this silently picked the
        // EARLIEST phase below the current one as "previous" instead of the closest one —
        // e.g. phase 4's opening balance came from phase 1's closing, completely dropping
        // phases 2 and 3's contributions from the cumulative total. reorder() clears the
        // relationship's default ordering before applying the one this method actually needs.
        $previous = $root->phases()->reorder()->where(function ($query) use ($phase) {
            $query->where('sort_order', '<', $phase->sort_order)
                ->orWhere(fn ($same) => $same->where('sort_order', $phase->sort_order)->where('id', '<', $phase->id));
        })->orderByDesc('sort_order')->orderByDesc('id')->first();
        if (! $previous) {
            return collect();
        }

        abort_unless($previous->results_published, 422, 'Publish and lock the previous phase before carrying points forward.');
        $version = (int) FestPhaseScoreSnapshot::where('phase_id', $previous->id)
            ->whereNull('invalidated_at')->max('version');
        // A published phase may legitimately have no scored schools. In that case its
        // closing balance is the empty/zero map and the next phase still opens at zero.
        if ($version < 1) {
            return collect();
        }

        return FestPhaseScoreSnapshot::where('phase_id', $previous->id)->where('version', $version)
            ->get()->mapWithKeys(fn (FestPhaseScoreSnapshot $row) => [
                $this->mapKey($row->school_id, $row->championship_category_key) => (float) $row->closing_points,
            ]);
    }

    private function sameAsLatest(FestEventPhase $phase, int $version, Collection $snapshots, Collection $events): bool
    {
        $existingSnapshots = FestPhaseScoreSnapshot::where('phase_id', $phase->id)->where('version', $version)->get()
            ->map(fn ($row) => [$row->school_id, $row->championship_category_key, (float) $row->opening_points, (float) $row->current_points, (float) $row->closing_points])->sort()->values()->all();
        $newSnapshots = $snapshots->map(fn ($row) => [$row['school_id'], $row['championship_category_key'], (float) $row['opening_points'], (float) $row['current_points'], (float) $row['closing_points']])->sort()->values()->all();
        $existingEvents = FestScoreContribution::where('phase_id', $phase->id)->where('version', $version)->get()
            ->map(fn ($row) => [$row->source_event_id, $row->school_id, $row->championship_category_key, (float) $row->points])->sort()->values()->all();
        $newEvents = $events->map(fn ($row) => [$row['source_event_id'], $row['school_id'], $row['championship_category_key'], (float) $row['points']])->sort()->values()->all();

        return $existingSnapshots === $newSnapshots && $existingEvents === $newEvents;
    }

    private function applyRanks(Collection $rows): Collection
    {
        return $rows->groupBy('championship_category_key')->flatMap(function (Collection $categoryRows) {
            $position = 0;
            $rank = 0;
            $previous = null;

            return $categoryRows->sortByDesc('closing_points')->values()->map(function (array $row) use (&$position, &$rank, &$previous) {
                $position++;
                if ($previous === null || $row['closing_points'] < $previous) {
                    $rank = $position;
                }
                $previous = $row['closing_points'];
                $row['rank'] = $rank;

                return $row;
            });
        })->values();
    }

    private function mapKey(string $schoolId, string $category): string
    {
        return $schoolId.'|'.$category;
    }

    private function championshipCategoryKey(FestEvent $root, FestEvent $sourceEvent, string $sourceCategory): string
    {
        $mapping = ($root->aggregation_config ?? [])['championship_category_map'] ?? [];
        $phaseMapping = $mapping[(string) $sourceEvent->source_phase_id] ?? [];

        return (string) ($phaseMapping[$sourceCategory] ?? $mapping[$sourceCategory] ?? $sourceCategory);
    }
}
