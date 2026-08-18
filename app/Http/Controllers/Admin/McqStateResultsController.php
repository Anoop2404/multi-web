<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use Illuminate\Http\Request;

class McqStateResultsController extends Controller
{
    public function index(Request $request)
    {
        $clusterId = $request->query('cluster');

        $sahodayas = Tenant::where('type', 'sahodaya')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // mcq_exams/mcq_registrations live in each Sahodaya cluster's own database
        // (TENANCY_DATABASE_PER_SAHODAYA), not the central one — same loop-per-cluster
        // pattern as SportsResultsController::index().
        $clustersToScan = $clusterId
            ? $sahodayas->where('id', $clusterId)
            : $sahodayas;

        $results = collect();
        foreach ($clustersToScan as $sahodaya) {
            $clusterResults = $sahodaya->run(function () use ($sahodaya) {
                $exams = McqExam::query()
                    ->where('results_published', true)
                    ->orderByDesc('result_date')
                    ->get(['id', 'title', 'tenant_id', 'result_date']);

                $rows = collect();
                foreach ($exams as $exam) {
                    $examRows = McqRegistration::where('exam_id', $exam->id)
                        ->with(['student:id,name', 'school:id,name', 'mark'])
                        ->get()
                        ->filter(fn (McqRegistration $r) => $r->mark && $r->mark->rank !== null)
                        ->sortBy(fn (McqRegistration $r) => $r->mark->rank)
                        ->map(fn (McqRegistration $r) => [
                            'exam'        => $exam->title,
                            'cluster'     => $sahodaya->name,
                            'hall_ticket' => $r->hall_ticket_no,
                            'participant' => $r->student?->name,
                            'school'      => $r->school?->name,
                            'score'       => $r->mark?->score,
                            'percentage'  => $r->mark?->percentage,
                            'grade'       => $r->mark?->grade,
                            'rank'        => $r->mark?->rank,
                        ])
                        ->values();

                    $rows = $rows->merge($examRows);
                }

                return $rows;
            });

            $results = $results->merge($clusterResults);
        }

        return inertia('State/Mcq/Results', [
            'sahodayas' => $sahodayas,
            'results'   => $results->take(500)->values(),
            'filters'   => [
                'cluster' => $clusterId,
            ],
        ]);
    }
}
