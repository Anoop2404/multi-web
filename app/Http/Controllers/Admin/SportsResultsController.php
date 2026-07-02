<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SportsResultsController extends Controller
{
    public function index(Request $request)
    {
        $clusterId = $request->query('cluster');
        $ageGroup = $request->query('age_group');
        $gender = $request->query('gender');

        $sahodayas = Tenant::where('type', 'sahodaya')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $query = FestEvent::query()
            ->where('event_type', 'sports')
            ->where('results_published', true);

        if ($clusterId) {
            $query->where('tenant_id', $clusterId);
        }

        $events = $query->orderByDesc('event_start')->get(['id', 'title', 'tenant_id', 'event_start']);
        $sahodayaNames = Tenant::whereIn('id', $events->pluck('tenant_id')->unique())->pluck('name', 'id');

        $results = collect();
        foreach ($events as $event) {
            $clusterName = $sahodayaNames[$event->tenant_id] ?? '—';
            $marks = FestMark::where('event_id', $event->id)
                ->whereNotNull('position')
                ->with(['participant.student', 'participant.registration.school', 'item'])
                ->orderBy('item_id')
                ->orderBy('position')
                ->get()
                ->filter(function (FestMark $m) use ($ageGroup, $gender) {
                    if ($ageGroup && ($m->item?->age_group ?? '') !== $ageGroup) {
                        return false;
                    }
                    if ($gender && ($m->item?->gender ?? '') !== $gender) {
                        return false;
                    }

                    return true;
                })
                ->map(fn (FestMark $m) => [
                    'event'       => $event->title,
                    'cluster'     => $clusterName,
                    'item'        => $m->item?->title,
                    'age_group'   => $m->item?->age_group,
                    'gender'      => $m->item?->gender,
                    'position'    => $m->position,
                    'measurement' => $m->measurement_value ? "{$m->measurement_value} {$m->measurement_unit}" : null,
                    'participant' => $m->participant?->student?->name,
                    'school'      => $m->participant?->registration?->school?->name,
                ]);

            $results = $results->merge($marks);
        }

        return inertia('State/Sports/Results', [
            'sahodayas'  => $sahodayas,
            'results'    => $results->take(500)->values(),
            'filters'    => [
                'cluster'   => $clusterId,
                'age_group' => $ageGroup,
                'gender'    => $gender,
            ],
            'ageGroups'  => config('fest_sports_age_groups.groups', []),
        ]);
    }
}
