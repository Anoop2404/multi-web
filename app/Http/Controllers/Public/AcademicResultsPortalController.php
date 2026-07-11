<?php

namespace App\Http\Controllers\Public;

use App\Models\AcademicAward;
use App\Models\BoardResult;
use App\Models\BoardResultRanking;
use App\Models\Tenant;
use App\Models\Topper;
use App\Services\BoardResults\RankingEngine;
use App\Support\AcademicYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AcademicResultsPortalController extends Controller
{
    public function index(Request $request)
    {
        $tenant = tenant();
        abort_unless($tenant && $tenant->type === 'sahodaya', 404);

        $year = $request->string('year')->toString() ?: AcademicYear::forSahodaya($tenant->id);
        $q = trim($request->string('q')->toString());

        $schoolIds = Tenant::query()
            ->where('parent_id', $tenant->id)
            ->where('type', 'school')
            ->pluck('id', 'name');

        $years = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds->values())
            ->published()
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        $rankings = BoardResultRanking::query()
            ->where('sahodaya_id', $tenant->id)
            ->where('academic_year', $year)
            ->where('scope', RankingEngine::SCOPE_OVERALL_PASS_PERCENT)
            ->where('entity_type', 'school')
            ->orderBy('rank')
            ->limit(50)
            ->get();

        $schoolNames = Tenant::whereIn('id', $rankings->pluck('entity_id'))->pluck('name', 'id');

        $toppers = Topper::query()
            ->whereHas('boardResult', fn ($query) => $query
                ->whereIn('tenant_id', $schoolIds->values())
                ->where('academic_year', $year)
                ->published())
            ->when($q !== '', function ($query) use ($q, $schoolIds) {
                $matchingSchoolIds = $schoolIds->filter(
                    fn ($id, $name) => str_contains(strtolower((string) $name), strtolower($q))
                )->values();
                $query->where(function ($inner) use ($q, $matchingSchoolIds) {
                    $inner->where('name', 'ilike', "%{$q}%")
                        ->orWhere('admission_no', 'ilike', "%{$q}%")
                        ->orWhere('roll_no', 'ilike', "%{$q}%");
                    if ($matchingSchoolIds->isNotEmpty()) {
                        $inner->orWhereIn('tenant_id', $matchingSchoolIds);
                    }
                });
            })
            ->with(['boardResult', 'examStream'])
            ->orderByDesc('percentage')
            ->limit(100)
            ->get();

        $awards = AcademicAward::query()
            ->where('sahodaya_id', $tenant->id)
            ->where('academic_year', $year)
            ->get();

        return view('public.academic-results.index', [
            'sahodaya' => $tenant,
            'year' => $year,
            'years' => $years,
            'q' => $q,
            'rankings' => $rankings->map(fn (BoardResultRanking $r) => [
                'rank' => $r->rank,
                'school' => $schoolNames[$r->entity_id] ?? $r->entity_id,
                'score' => $r->score,
                'pass_percent' => $r->meta['pass_percent'] ?? $r->score,
                'class' => $r->class,
            ]),
            'toppers' => $toppers,
            'schoolNames' => Tenant::whereIn('id', $toppers->pluck('tenant_id'))->pluck('name', 'id'),
            'awards' => $awards,
            'awardSchoolNames' => Tenant::whereIn('id', $awards->pluck('tenant_id')->filter())->pluck('name', 'id'),
        ]);
    }

    public function meritListPdf(Request $request)
    {
        $tenant = tenant();
        abort_unless($tenant && $tenant->type === 'sahodaya', 404);

        $year = $request->string('year')->toString() ?: AcademicYear::forSahodaya($tenant->id);
        $class = $request->integer('class') ?: null;

        $schoolIds = Tenant::query()
            ->where('parent_id', $tenant->id)
            ->where('type', 'school')
            ->pluck('id');

        $toppers = Topper::query()
            ->whereHas('boardResult', function ($query) use ($schoolIds, $year, $class) {
                $query->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $year)
                    ->published();
                if ($class) {
                    $query->where('class', $class);
                }
            })
            ->with(['boardResult', 'examStream'])
            ->orderByDesc('percentage')
            ->orderBy('rank')
            ->limit(500)
            ->get();

        $schoolNames = Tenant::whereIn('id', $toppers->pluck('tenant_id'))->pluck('name', 'id');

        return Pdf::loadView('public.academic-results.merit-list-pdf', [
            'sahodaya' => $tenant,
            'year' => $year,
            'class' => $class,
            'toppers' => $toppers,
            'schoolNames' => $schoolNames,
        ])->download("merit-list-{$year}".($class ? "-class{$class}" : '').'.pdf');
    }
}
