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
        if ($this->event->event_type !== 'kids_fest' || $this->event->parent_event_id) {
            return [];
        }

        return app(FestKidsFestClusterService::class)
            ->clusters($this->event)
            ->map(fn (FestEvent $c) => $c->cluster_key)
            ->filter()
            ->values()
            ->all();
    }

    public function scoreboardClusterLabel(string $clusterKey): string
    {
        $cluster = FestEvent::where('parent_event_id', $this->event->id)
            ->where('cluster_key', $clusterKey)
            ->first();

        return $cluster?->cluster_label ?? ucfirst(str_replace('-', ' ', $clusterKey));
    }

    /** @return list<array{school_id: string, school_name: string, total_points: int, rank: int}> */
    public function scoreboardByCluster(string $clusterKey): array
    {
        $cluster = FestEvent::where('parent_event_id', $this->event->id)
            ->where('cluster_key', $clusterKey)
            ->first();

        if (! $cluster) {
            return [];
        }

        return self::for($cluster)->scoreboardBySchool();
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

        return ['lp', 'up', 'hs', 'hss'];
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

        return config("fest_item_taxonomy.class_group.{$category}", strtoupper($category));
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

        $marks = $marksQuery->get();
        $pointsBySchool = [];

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $schoolId = $participant->registration?->school_id;
            if (! $schoolId) {
                continue;
            }

            $pointsBySchool[$schoolId] = ($pointsBySchool[$schoolId] ?? 0)
                + $gradePointService->pointsForMark($this->event, $mark);
        }

        if ($pointsBySchool === []) {
            return [];
        }

        $schools = Tenant::whereIn('id', array_keys($pointsBySchool))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        $rank = 1;
        $previousTotal = null;
        $position = 0;
        $rows = [];

        foreach (collect($pointsBySchool)->sortDesc() as $schoolId => $total) {
            $position++;
            if ($previousTotal !== null && (int) $total < (int) $previousTotal) {
                $rank = $position;
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

    public function scoreboardBySchool(): array
    {
        $clusterService = app(FestKidsFestClusterService::class);
        if ($clusterService->isUmbrella($this->event)) {
            return $clusterService->combinedScoreboard($this->event);
        }

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

        $marks = FestMark::where('event_id', $this->event->id)
            ->with(['participant.registration'])
            ->get();

        $pointsBySchool = [];

        foreach ($marks as $mark) {
            $participant = $mark->participant;
            if (! $participant || $participant->disqualified_at) {
                continue;
            }

            $schoolId = $participant->registration?->school_id;
            if (! $schoolId) {
                continue;
            }

            $pointsBySchool[$schoolId] = ($pointsBySchool[$schoolId] ?? 0)
                + $gradePointService->pointsForMark($this->event, $mark);
        }

        $rank = 1;
        $previousTotal = null;
        $position = 0;

        foreach (collect($pointsBySchool)->sortDesc() as $schoolId => $total) {
            $position++;
            if ($previousTotal !== null && (int) $total < (int) $previousTotal) {
                $rank = $position;
            }
            $previousTotal = (int) $total;

            FestResult::updateOrCreate(
                ['event_id' => $this->event->id, 'item_id' => null, 'school_id' => $schoolId],
                ['total_points' => (int) $total, 'rank' => $rank]
            );
        }
    }
}
