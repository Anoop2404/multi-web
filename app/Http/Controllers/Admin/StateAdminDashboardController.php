<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Services\Events\StateDashboardService;
use App\Support\AcademicYear;
use Inertia\Response;

class StateAdminDashboardController extends Controller
{
    public function index(StateDashboardService $dashboard): Response
    {
        // State-level "current" academic year: the state-wide active AcademicYearRecord if one
        // is set, otherwise the calendar-derived year (AcademicYear::forSahodaya(null) checks
        // activeRecordLabel() first, same as the previous activeAcademicYear computation below).
        // This is the same value already shown to the user as the "Academic year" badge — scope
        // the dashboard's data to match it instead of mixing every year's programs/remittances
        // together. There's no state-level year selector in the UI yet (see
        // resources/js/Pages/Admin/State/Dashboard.vue), so this is the simplest correct default
        // rather than a full year-switcher.
        $academicYear = AcademicYear::forSahodaya(null);

        $programs = FestStateProgram::query()->where('academic_year', $academicYear)->get();
        $remittances = StateRemittance::query()->where('academic_year', $academicYear)->get();

        return inertia('State/Dashboard', [
            'activeAcademicYear' => $academicYear,
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
