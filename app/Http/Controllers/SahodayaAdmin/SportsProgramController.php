<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;
use App\Models\Certificate;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
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

        return $this->inertia('Sahodaya/Sports/Championship', [
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

        return $this->inertia('Sahodaya/Sports/Results', [
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
            ->get(['id', 'title']);

        $schoolIds = FestRegistration::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->where('status', 'approved')
            ->distinct()
            ->pluck('school_id');

        $schools = Tenant::whereIn('id', $schoolIds)->get(['id', 'name'])->keyBy('id');

        $rows = FestMark::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->whereNotNull('position')
            ->where('position', '<=', 3)
            ->with('participant.registration:id,school_id')
            ->get()
            ->groupBy(fn (FestMark $mark) => $mark->participant?->registration?->school_id)
            ->map(function ($marks, $schoolId) use ($schools) {
                $gold = $marks->where('position', 1)->count();
                $silver = $marks->where('position', 2)->count();
                $bronze = $marks->where('position', 3)->count();

                return [
                    'school_id'   => $schoolId,
                    'school_name' => $schools->get($schoolId)?->name ?? 'School',
                    'gold'        => $gold,
                    'silver'      => $silver,
                    'bronze'      => $bronze,
                    'points'      => ($gold * 5) + ($silver * 3) + ($bronze * 1),
                ];
            })
            ->filter(fn ($row) => $row['school_id'])
            ->sortByDesc('points')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });

        return $this->inertia('Sahodaya/Sports/Rankings', [
            'events'   => $events,
            'rankings' => $rows,
        ]);
    }
}
