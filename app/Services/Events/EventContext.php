<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestHouse;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\FestOverallCategoryExclusion;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Collection;

class EventContext
{
    public function __construct(
        public FestEvent $event,
    ) {}

    public static function for(FestEvent $event): self
    {
        return new self($event);
    }

    public function items(): Collection
    {
        return $this->event->items;
    }

    public function approvedRegistrations(?FestEventItem $item = null): Collection
    {
        $q = FestRegistration::where('event_id', $this->event->id)
            ->where('status', 'approved');

        if ($item) {
            $q->where('item_id', $item->id);
        }

        return $q->with(['participants.student', 'participants.teacher'])->get();
    }

    public function nextChestNumber(FestEventItem $item): int
    {
        return app(FestNumberingService::class)->nextChestNumber($this->event, $item);
    }

    /** @return list<string> */
    public function scoreboardClusters(): array
    {
        $partitionService = app(FestPartitionService::class);

        if (! $partitionService->isPartitionedHub($this->event)) {
            return [];
        }

        return $partitionService->partitionKeys($this->event);
    }

    public function scoreboardClusterLabel(string $clusterKey): string
    {
        return app(FestPartitionService::class)->partitionLabel($this->event, $clusterKey);
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboardByCluster(string $clusterKey): array
    {
        return app(FestPartitionService::class)->scoreboardByPartition($this->event, $clusterKey);
    }

    /** @return list<string> */
    public function scoreboardCategories(): array
    {
        if ($this->event->event_type === 'sports') {
            return FestEventItem::where('event_id', $this->event->id)
                ->where('is_enabled', true)
                ->whereNotNull('age_group')
                ->where('age_group', '!=', 'open')
                ->distinct()
                ->orderBy('age_group')
                ->pluck('age_group')
                ->values()
                ->all();
        }

        $available = FestEventItem::where('event_id', $this->event->id)
            ->where('is_enabled', true)
            ->whereNotNull('class_group')
            ->where('class_group', '!=', 'open')
            ->distinct()
            ->pluck('class_group')
            ->values();

        if ($available->isEmpty()) {
            return array_values(array_filter(
                array_keys(FestClassGroupScheme::labels(null, $this->event->rootEvent())),
                fn (string $key) => $key !== 'open',
            ));
        }

        $configuredOrder = collect(array_keys(FestClassGroupScheme::labels(null, $this->event)))
            ->filter(fn (string $key) => $key !== 'open' && $available->contains($key));

        return $configuredOrder
            ->concat($available->diff($configuredOrder))
            ->values()
            ->all();
    }

    public function scoreboardCategoryLabel(?string $category): string
    {
        if (! $category) {
            return 'Overall';
        }

        if ($this->event->event_type === 'sports') {
            return FestSportsAgeGroup::labels($this->event->tenant_id)[$category]
                ?? strtoupper($category);
        }

        return FestClassGroupScheme::resolveItemLabel(FestClassGroupScheme::labels(null, $this->event->rootEvent()), $category);
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboardByCategory(?string $category = null): array
    {
        if (! $category) {
            return $this->scoreboardBySchool();
        }

        $gradePointService = app(FestGradePointService::class);

        $marksQuery = FestMark::where('event_id', $this->event->id)
            ->with(['participant.registration', 'item']);

        if ($this->event->event_type === 'sports') {
            $marksQuery->whereHas('item', fn ($q) => $q->where('age_group', $category));
        } else {
            $marksQuery->whereHas('item', fn ($q) => $q->where('class_group', $category));
        }

        // Pair/group items save one FestMark per teammate (all sharing the same
        // registration_id and the same position/score) — sum each registration once,
        // not once per teammate, or a team's points scale with its squad size.
        $marks = $marksQuery->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());
        $pointsBySchool = [];
        $appealPoolIds = Tenant::appealPoolSchoolIds();

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $schoolId = $participant->registration?->school_id;
            if (! $schoolId || isset($appealPoolIds[$schoolId])) {
                continue;
            }

            $pointsBySchool[$schoolId] = ($pointsBySchool[$schoolId] ?? 0)
                + $gradePointService->pointsForMark($this->event, $mark);
        }

        return $this->rankPointsBySchool($pointsBySchool);
    }

    public function scoreboardBySchool(): array
    {
        $partitionService = app(FestPartitionService::class);
        if ($partitionService->isPartitionedHub($this->event) && $partitionService->shouldCombineAtFinale($this->event)) {
            return $partitionService->combinedScoreboard($this->event);
        }

        return $this->scoreboardBySchoolForEvent();
    }

    /**
     * §7.3a (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15) — a school's points
     * for items where item.phase_id = $phaseId, computed live from this event's own
     * FestMark rows (mirrors scoreboardByCategory()'s live class_group/age_group
     * filter, applied to phase_id instead — and combinable with it via $category, so
     * the same phase-cumulative view FestPhaseScoreboardService builds for "Overall"
     * can be built per class/age category too). Used directly by
     * FestPhaseScoreboardService::phaseScoreboard() for a non-regional phase (called
     * on the hub event), and once per region-partition child for a regional phase
     * (called on each child, then summed via
     * FestPartitionService::aggregateScoreboardAcrossPartitions()).
     *
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    public function scoreboardByPhase(int $phaseId, ?string $category = null): array
    {
        $gradePointService = app(FestGradePointService::class);
        $categoryColumn = $this->event->event_type === 'sports' ? 'age_group' : 'class_group';
        // Only the combined "All Categories" total (no specific $category requested)
        // honors excluded_overall_categories — same rule as scoreboardBySchoolForEvent()'s
        // snapshot (recalculateSchoolPoints()) and provisionalScoreboard(): a category's
        // own board still counts its points, only the combined phase/cumulative total
        // skips it. This is the phased-event path (FestPhaseScoreboardService ->
        // phaseScoreboard()/cumulativeOverall()), which previously never checked the
        // exclusion at all — a phased event's public School-wise total ignored an
        // admin's configured exclusions entirely, unlike a non-phased event's.
        $excludedCategories = $category ? [] : FestOverallCategoryExclusion::excluded($this->event->rootEvent());

        // Same dedupe as scoreboardByCategory() above — one FestMark per teammate on
        // pair/group items must not multiply a team's points by its squad size.
        //
        // Also, unlike scoreboardByCategory() above, this filters to items that have
        // actually published their own results (and aren't later explicitly hidden) —
        // mirrors the fix already applied to PublicFestScoreboardService::scoreboard()'s
        // category branch (the "item only counts once its own results have been
        // published" comment there) and recalculateSchoolPoints() below, which this
        // phased-event path never got: a phased event's public School-wise total was
        // silently including marks from items nobody had published yet, or that were
        // later hidden, the moment the phase itself was marked published.
        $marks = FestMark::where('event_id', $this->event->id)
            ->whereHas('item', function ($q) use ($phaseId, $category, $categoryColumn, $excludedCategories) {
                $q->where('phase_id', $phaseId)
                    ->whereNotNull('results_published_at')
                    ->where('results_hidden', false);
                if ($category) {
                    $q->where($categoryColumn, $category);
                }
                if ($excludedCategories) {
                    $q->whereNotIn($categoryColumn, $excludedCategories);
                }
            })
            ->with(['participant.registration', 'item'])
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        $pointsBySchool = [];
        $appealPoolIds = Tenant::appealPoolSchoolIds();

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $schoolId = $participant->registration?->school_id;
            if (! $schoolId || isset($appealPoolIds[$schoolId])) {
                continue;
            }

            $pointsBySchool[$schoolId] = ($pointsBySchool[$schoolId] ?? 0)
                + $gradePointService->pointsForMark($this->event, $mark);
        }

        return $this->rankPointsBySchool($pointsBySchool);
    }

    /**
     * @param  array<string, int|float>  $pointsBySchool
     * @return list<array{school_id: string, school_name: string, total_points: int, rank: int}>
     */
    private function rankPointsBySchool(array $pointsBySchool): array
    {
        if ($pointsBySchool === []) {
            return [];
        }

        $schools = Tenant::whereIn('id', array_keys($pointsBySchool))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        $rank = 0;
        $previousTotal = null;
        $rows = [];

        foreach (collect($pointsBySchool)->sortDesc() as $schoolId => $total) {
            if ($previousTotal === null || (int) $total < (int) $previousTotal) {
                $rank++;
            }
            $previousTotal = (int) $total;

            $rows[] = [
                'school_id'    => $schoolId,
                'school_name'  => $schools[$schoolId]?->name ?? $schoolId,
                'total_points' => (int) $total,
                'rank'         => $rank,
            ];
        }

        return $rows;
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboardBySchoolForEvent(): array
    {
        $schoolIds = FestResult::where('event_id', $this->event->id)
            ->whereNull('item_id')
            ->pluck('school_id', 'school_id');

        if ($schoolIds->isEmpty()) {
            $schoolIds = FestRegistration::where('event_id', $this->event->id)
                ->where('status', 'approved')
                ->pluck('school_id', 'school_id');
        }

        $schools = Tenant::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']);

        return FestResult::where('event_id', $this->event->id)
            ->whereNull('item_id')
            ->orderBy('rank')
            ->get()
            ->map(fn (FestResult $r) => [
                'school_id'    => $r->school_id,
                'school_name'  => $schools->firstWhere('id', $r->school_id)?->name ?? $r->school_id,
                'total_points' => $r->total_points,
                'rank'         => $r->rank,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{house_id: int, house_name: string, color: ?string, total_points: int, rank: int}> */
    public function scoreboardByHouse(): array
    {
        $houses = FestHouse::where('event_id', $this->event->id)
            ->with('schoolAssignments')
            ->orderBy('sort_order')
            ->get();

        if ($houses->isEmpty()) {
            return [];
        }

        $schoolPoints = FestResult::where('event_id', $this->event->id)
            ->whereNull('item_id')
            ->pluck('total_points', 'school_id');

        $totals = [];
        foreach ($houses as $house) {
            $points = 0;
            foreach ($house->schoolAssignments as $assignment) {
                $points += (int) ($schoolPoints[$assignment->school_id] ?? 0);
            }
            $totals[] = [
                'house_id'     => $house->id,
                'house_name'   => $house->name,
                'color'        => $house->color,
                'total_points' => $points,
            ];
        }

        usort($totals, fn ($a, $b) => $b['total_points'] <=> $a['total_points']);
        foreach ($totals as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return $totals;
    }

    public function recalculateSchoolPoints(): void
    {
        $gradePointService = app(FestGradePointService::class);

        // Same dedupe as scoreboardByCategory()/scoreboardByPhase() above.
        // Unconditional, regardless of whether the event overall is published — an item
        // that was never individually published (still "Pending"/"Marks pending" in
        // admin) must never contribute to a school's official total/rank just because
        // the event-wide toggle went on. Previously bypassed once $isOverallPublished
        // was true, so a school's real ranking/certificate could silently include marks
        // from an item that was never actually published, or one explicitly left
        // unpublished after testing.
        $marks = FestMark::where('event_id', $this->event->id)
            ->whereHas('item', fn ($q) => $q->whereNotNull('results_published_at'))
            ->with(['participant.registration.item', 'item'])
            ->get()
            ->unique(fn (FestMark $m) => $m->deduplicationKey());

        $pointsBySchool = [];
        $appealPoolIds = Tenant::appealPoolSchoolIds();
        $excludedCategories = FestOverallCategoryExclusion::excluded($this->event->rootEvent());

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $schoolId = $participant->registration?->school_id;
            if (! $schoolId || isset($appealPoolIds[$schoolId])) {
                continue;
            }

            // Admin-configured "leave this category out of the combined overall total"
            // (aggregation_config.excluded_overall_categories) — the category's own
            // scoreboard tab (scoreboardByCategory()) is unaffected, only this flat total.
            if ($excludedCategories && in_array(FestOverallCategoryExclusion::categoryKeyForItem($this->event, $mark->item), $excludedCategories, true)) {
                continue;
            }

            $pointsBySchool[$schoolId] = ($pointsBySchool[$schoolId] ?? 0)
                + $gradePointService->pointsForMark($this->event, $mark);
        }

        if (empty($pointsBySchool)) {
            FestResult::where('event_id', $this->event->id)->whereNull('item_id')->delete();
            return;
        }

        $rank = 0;
        $previousTotal = null;

        $existingSchoolIds = FestResult::where('event_id', $this->event->id)->whereNull('item_id')->pluck('school_id')->all();
        $updatedSchoolIds = [];

        foreach (collect($pointsBySchool)->sortDesc() as $schoolId => $total) {
            if ($previousTotal === null || (int) $total < (int) $previousTotal) {
                $rank++;
            }
            $previousTotal = (int) $total;
            $updatedSchoolIds[] = $schoolId;

            FestResult::updateOrCreate(
                ['event_id' => $this->event->id, 'item_id' => null, 'school_id' => $schoolId],
                ['total_points' => (int) $total, 'rank' => $rank]
            );
        }

        $staleSchoolIds = array_diff($existingSchoolIds, $updatedSchoolIds);
        if (! empty($staleSchoolIds)) {
            FestResult::where('event_id', $this->event->id)->whereNull('item_id')->whereIn('school_id', $staleSchoolIds)->delete();
        }
    }
}
