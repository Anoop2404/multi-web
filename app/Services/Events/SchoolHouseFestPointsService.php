<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\SchoolHouse;

class SchoolHouseFestPointsService
{
    public function __construct(
        private FestGradePointService $gradePointService,
    ) {}

    /** @return list<array{house_id: int, house_name: string, color: ?string, total_points: int, participants: int, rank: int}> */
    public function rankingForSchool(string $schoolId, ?int $eventId = null): array
    {
        $houses = SchoolHouse::forSchool($schoolId)->orderBy('sort_order')->orderBy('name')->get();
        $totals = [];
        $participantCounts = [];

        foreach ($houses as $house) {
            $totals[$house->id] = 0;
            $participantCounts[$house->id] = 0;
        }

        $marks = FestMark::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereHas('participant', fn ($q) => $q->whereHas('student', fn ($s) => $s
                ->where('tenant_id', $schoolId)
                ->whereNotNull('school_house_id')))
            ->with(['participant.student', 'participant.registration.item'])
            ->get();

        foreach ($marks as $mark) {
            $houseId = $mark->participant?->student?->school_house_id;
            if (! $houseId || ! array_key_exists($houseId, $totals)) {
                continue;
            }

            $event = FestEvent::find($mark->event_id);
            if (! $event) {
                continue;
            }

            $points = $this->gradePointService->pointsForMark($event, $mark);
            $totals[$houseId] += $points;
            if ($points > 0 || $mark->position || $mark->grade) {
                $participantCounts[$houseId]++;
            }
        }

        $rows = $houses->map(fn (SchoolHouse $house) => [
            'house_id'     => $house->id,
            'house_name'   => $house->name,
            'color'        => $house->color,
            'total_points' => (int) ($totals[$house->id] ?? 0),
            'participants' => (int) ($participantCounts[$house->id] ?? 0),
        ])->sortByDesc('total_points')->values()->all();

        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function openEventsForSchool(string $schoolId, string $sahodayaId): array
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->whereHas('registrations', fn ($q) => $q->where('school_id', $schoolId)->whereIn('status', ['submitted', 'approved']))
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'level_round', 'results_published'])
            ->map(fn (FestEvent $e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'level' => $e->level_round,
            ])
            ->all();
    }
}
