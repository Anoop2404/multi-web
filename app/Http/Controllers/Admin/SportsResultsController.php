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

        // fest_events/fest_marks live in each Sahodaya cluster's own database
        // (TENANCY_DATABASE_PER_SAHODAYA), not the central one — so this has to
        // run the query once per cluster, inside that cluster's own connection,
        // same pattern as the tenant-iterating console commands (e.g.
        // AuditPaymentIntegrity::handle()) use.
        $clustersToScan = $clusterId
            ? $sahodayas->where('id', $clusterId)
            : $sahodayas;

        $results = collect();
        foreach ($clustersToScan as $sahodaya) {
            $clusterResults = $sahodaya->run(function () use ($sahodaya, $ageGroup, $gender) {
                $events = FestEvent::query()
                    ->where('event_type', 'sports')
                    ->where('results_published', true)
                    ->where('tenant_id', $sahodaya->id)
                    ->orderByDesc('event_start')
                    ->get(['id', 'title', 'tenant_id', 'event_start']);

                $clusterMarks = collect();
                foreach ($events as $event) {
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
                            'cluster'     => $sahodaya->name,
                            'item'        => $m->item?->title,
                            'age_group'   => $m->item?->age_group,
                            'gender'      => $m->item?->gender,
                            'position'    => $m->position,
                            'measurement' => $m->measurement_value ? "{$m->measurement_value} {$m->measurement_unit}" : null,
                            'participant' => $m->participant?->student?->name,
                            'school'      => $m->participant?->registration?->school?->name,
                        ]);

                    $clusterMarks = $clusterMarks->merge($marks);
                }

                return $clusterMarks;
            });

            $results = $results->merge($clusterResults);
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
