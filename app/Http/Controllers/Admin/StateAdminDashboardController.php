<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Services\Events\StateDashboardService;
use Inertia\Response;

class StateAdminDashboardController extends Controller
{
    public function index(StateDashboardService $dashboard): Response
    {
        $programs = FestStateProgram::query()->get();
        $remittances = StateRemittance::query()->get();

        return inertia('State/Dashboard', [
            'stats' => [
                'total_programs'       => $programs->count(),
                'draft_programs'       => $programs->where('status', 'draft')->count(),
                'published_programs'   => $programs->where('status', 'published')->count(),
                'total_remittances'    => $remittances->count(),
                'pending_remittances'  => $remittances->whereIn('status', ['pending', 'submitted'])->count(),
                'verified_remittances' => $remittances->where('status', 'verified')->count(),
                'sahodaya_clusters'    => Tenant::where('type', 'sahodaya')->count(),
            ],
            'recentRemittances' => $remittances->sortByDesc('created_at')->take(8)->values()->map(fn (StateRemittance $r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'amount'      => $r->amount,
                'status'      => $r->status,
                'due_date'    => $r->due_date?->toDateString(),
                'sahodaya_id' => $r->sahodaya_id,
            ]),
            'recentPrograms' => $programs->sortByDesc('created_at')->take(6)->values()->map(fn (FestStateProgram $p) => [
                'id'           => $p->id,
                'title'        => $p->title,
                'event_type'   => $p->event_type,
                'status'       => $p->status,
                'academic_year'=> $p->academic_year,
            ]),
            'propagation'    => $dashboard->propagationStatus()->take(8)->values(),
            'clusterRollup'  => $dashboard->clusterResultsRollup(),
            'participation'  => $dashboard->clusterParticipationRollup(),
        ]);
    }
}
