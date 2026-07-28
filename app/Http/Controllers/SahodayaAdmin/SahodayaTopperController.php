<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\TopperCountConfig;
use App\Services\BoardResults\SahodayaTopperSelectionService;
use App\Services\BoardResults\TopperCountService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

/**
 * Auto-computed Sahodaya-wide topper lists — pooled from every school's submitted toppers,
 * ranked centrally, then cut to the configured Top-N (see TopperCountConfig / TopperCountService).
 * This is distinct from BoardResultVerificationController, which handles per-school approval workflow.
 */
class SahodayaTopperController extends SahodayaAdminController
{
    /**
     * Settings hub: Top-N / tie-mode config + recompute, plus links out to the three
     * standalone report pages below. Deliberately doesn't compute any topper lists itself
     * so it stays fast regardless of how many schools/toppers exist.
     */
    public function index(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);

        [$overallConfig, $streamConfig] = $this->loadConfigs();

        return $this->inertia('Sahodaya/BoardResults/Toppers', [
            'selectedClass' => $class,
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'settings' => [
                'overall' => [
                    'top_n' => $overallConfig?->top_n ?? TopperCountService::DEFAULT_TOP_N,
                    'tie_mode' => $overallConfig?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP,
                ],
                'stream' => [
                    'top_n' => $streamConfig?->top_n ?? TopperCountService::DEFAULT_TOP_N,
                    'tie_mode' => $streamConfig?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP,
                ],
            ],
        ]);
    }

    /** Overall Result — Sahodaya-wide toppers (Class X flat, Class XII per stream). */
    public function overall(Request $request, SahodayaTopperSelectionService $selection)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);

        return $this->inertia('Sahodaya/BoardResults/TopperResultsOverall', [
            'selectedClass' => $class,
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'overall' => $class === 10 ? $selection->overallForClassX($this->sahodaya->id, $year) : [],
            'byStream' => $class === 12 ? $selection->byStreamForClassXII($this->sahodaya->id, $year) : [],
        ]);
    }

    /** Subject-Wise Top Scorers — Class XII only. */
    public function subjectWise(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $subjectLeaders = app(\App\Services\BoardResults\SubjectMeritRegisterService::class)
            ->register($this->sahodaya->id, $year, 12);

        return $this->inertia('Sahodaya/BoardResults/TopperResultsSubjectWise', [
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'subjectLeaders' => $subjectLeaders,
        ]);
    }

    /** 90%+ (or custom threshold) achievers — not capped to Top-N. */
    public function achievers(Request $request, SahodayaTopperSelectionService $selection)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);
        $threshold = $request->filled('threshold') ? (float) $request->input('threshold') : 90.0;

        return $this->inertia('Sahodaya/BoardResults/TopperResultsAchievers', [
            'selectedClass' => $class,
            'filters' => ['academic_year' => $year, 'threshold' => rtrim(rtrim(number_format($threshold, 2), '0'), '.')],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'achieversOverall' => $class === 10 ? $selection->achieversForClassX($this->sahodaya->id, $year, $threshold) : [],
            'achieversByStream' => $class === 12 ? $selection->achieversByStreamForClassXII($this->sahodaya->id, $year, $threshold) : [],
        ]);
    }

    /** @return array{0: ?TopperCountConfig, 1: ?TopperCountConfig} */
    private function loadConfigs(): array
    {
        $overallConfig = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_OVERALL)
            ->where(function ($q) { $q->where('class', 10)->orWhereNull('class'); })
            ->first();

        $streamConfig = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_STREAM)
            ->where(function ($q) { $q->where('class', 12)->orWhereNull('class'); })
            ->whereNull('stream_id')
            ->first();

        return [$overallConfig, $streamConfig];
    }

    public function recompute(Request $request, SahodayaTopperSelectionService $selection)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $result = $selection->recompute($this->sahodaya->id, $year);

        return back()->with('success', "Sahodaya toppers recomputed ({$result['rows']} rows).");
    }
}
