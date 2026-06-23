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
        $max = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $this->event->id)
            ->where('item_id', $item->id))
            ->max('chest_no');

        return ($max ?? 0) + 1;
    }

    public function scoreboardBySchool(): array
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
        $pointsBySchool = FestMark::where('event_id', $this->event->id)
            ->join('fest_participants', 'fest_marks.participant_id', '=', 'fest_participants.id')
            ->join('fest_registrations', 'fest_participants.registration_id', '=', 'fest_registrations.id')
            ->selectRaw('fest_registrations.school_id, SUM(COALESCE(fest_marks.score, 0)) as total')
            ->groupBy('fest_registrations.school_id')
            ->pluck('total', 'school_id');

        $ranked = $pointsBySchool->sortDesc()->values();
        $rank = 1;

        foreach ($pointsBySchool->sortDesc() as $schoolId => $total) {
            FestResult::updateOrCreate(
                ['event_id' => $this->event->id, 'item_id' => null, 'school_id' => $schoolId],
                ['total_points' => (int) $total, 'rank' => $rank++]
            );
        }
    }
}
