<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;
use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRankPoint;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestRankPointService;
use App\Services\Events\ProgramHubDataService;

class SportsProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'sports-meet';
    }

    public function championship(string $tenantId, ProgramHubDataService $hubData)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('sports')
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'results_published']);

        return $this->inertia('Sahodaya/Sports/Championship', $this->programNavProps('sports-meet') + [
            'events'          => $events,
            'houseStandings'  => $hubData->crossEventHouseStandings($this->sahodaya),
        ]);
    }

    public function results(string $tenantId)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('sports')
            ->where('results_published', true)
            ->orderByDesc('event_start')
            ->get(['id', 'title']);

        $results = FestMark::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->whereNotNull('position')
            ->with([
                'item:id,title',
                'participant.student:id,name,reg_no',
                'participant.registration.school:id,name',
            ])
            ->orderBy('event_id')
            ->orderBy('item_id')
            ->orderBy('position')
            ->get()
            ->map(fn (FestMark $mark) => [
                'event_id'    => $mark->event_id,
                'event_title' => $events->firstWhere('id', $mark->event_id)?->title,
                'item_title'  => $mark->item?->title,
                'student_name'=> $mark->participant?->student?->name,
                'reg_no'      => $mark->participant?->student?->reg_no,
                'school_name' => $mark->participant?->registration?->school?->name,
                'position'    => $mark->position,
                'score'       => $mark->score,
                'measurement' => trim(($mark->measurement_value ?? '').' '.($mark->measurement_unit ?? '')),
            ])
            ->values();

        return $this->inertia('Sahodaya/Sports/Results', $this->programNavProps('sports-meet') + [
            'events'  => $events,
            'results' => $results,
        ]);
    }

    public function rankings(string $tenantId)
    {
        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType('sports')
            ->where('results_published', true)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type']);

        $schoolIds = FestRegistration::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->where('status', 'approved')
            ->distinct()
            ->pluck('school_id');

        $schools = Tenant::whereIn('id', $schoolIds)->get(['id', 'name'])->keyBy('id');
        $eventsById = $events->keyBy('id');
        $configuredEventIds = FestRankPoint::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->distinct()
            ->pluck('event_id')
            ->flip();
        $rankPoints = app(FestRankPointService::class);

        $rows = FestMark::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->whereNotNull('position')
            ->with([
                'participant.registration:id,school_id',
                'item:id,participant_type',
            ])
            ->get()
            ->groupBy(fn (FestMark $mark) => $mark->participant?->registration?->school_id)
            ->map(function ($marks, $schoolId) use ($schools, $eventsById, $configuredEventIds, $rankPoints) {
                $gold = $marks->where('position', 1)->count();
                $silver = $marks->where('position', 2)->count();
                $bronze = $marks->where('position', 3)->count();

                $points = $marks->sum(function (FestMark $mark) use ($eventsById, $configuredEventIds, $rankPoints) {
                    $position = (int) $mark->position;
                    $event = $eventsById->get($mark->event_id);
                    if (! $event || $position < 1) {
                        return 0;
                    }

                    if ($configuredEventIds->has($mark->event_id)) {
                        $isGroup = in_array($mark->item?->participant_type, ['team', 'group'], true);

                        return $rankPoints->pointsForRank($event, $position, $isGroup);
                    }

                    // Legacy fallback when the event has no FestRankPoint rows configured.
                    return match ($position) {
                        1 => 5,
                        2 => 3,
                        3 => 1,
                        default => 0,
                    };
                });

                return [
                    'school_id'   => $schoolId,
                    'school_name' => $schools->get($schoolId)?->name ?? 'School',
                    'gold'        => $gold,
                    'silver'      => $silver,
                    'bronze'      => $bronze,
                    'points'      => (int) $points,
                ];
            })
            ->filter(fn ($row) => $row['school_id'])
            ->sortByDesc('points')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });

        return $this->inertia('Sahodaya/Sports/Rankings', $this->programNavProps('sports-meet') + [
            'events'   => $events,
            'rankings' => $rows,
        ]);
    }
}
