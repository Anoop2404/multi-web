<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\ExamStream;
use App\Models\Subject;
use App\Models\TopperRankingSetting;
use App\Services\BoardResults\SahodayaTopperSelectionService;
use App\Services\BoardResults\TopperCountService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

/**
 * Auto-computed Sahodaya-wide topper lists — pooled from every school's submitted toppers,
 * ranked centrally by percentage, uncapped (no Top-N/tie-break configuration — display is
 * plain percentage-sorted, with an optional no_rank/percentage-only mode via TopperRankingSetting).
 * This is distinct from BoardResultVerificationController, which handles per-school approval workflow.
 */
class SahodayaTopperController extends SahodayaAdminController
{
    /**
     * Sahodaya-wide topper list for one class/stream/year, plus links out to the three
     * standalone report pages below.
     */
    public function index(Request $request, SahodayaTopperSelectionService $selection)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);

        $streams = $this->streamOptions();
        $subjects = $this->subjectOptions();
        $selectedStream = $class === 12
            ? $this->resolveSelectedStreamCode($request, $streams)
            : null;
        $selectedStreamLabel = $selectedStream ? ($streams[$selectedStream] ?? null) : null;
        $selectedSubjectId = $this->resolveSelectedSubjectId($request, $subjects);

        $rankingSettings = TopperRankingSetting::forSahodaya($this->sahodaya->id);

        $overallRows = $class === 10 ? $selection->overallForClassX($this->sahodaya->id, $year) : [];
        $byStream = $class === 12 ? $selection->byStreamForClassXII($this->sahodaya->id, $year) : [];
        $streamRows = $class === 12 && $selectedStreamLabel
            ? ($byStream[$selectedStreamLabel] ?? [])
            : $overallRows;

        $schoolIds = \App\Models\Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $counts = [
            'full_a1' => \App\Models\Topper::query()
                ->join('board_results as br', 'br.id', '=', 'toppers.board_result_id')
                ->whereIn('br.tenant_id', $schoolIds)
                ->where('toppers.entry_type', \App\Models\Topper::ENTRY_FULL_A1)
                ->where('toppers.verification_status', 'verified')
                ->where('br.academic_year', $year)
                ->count(),
            'school_toppers' => \App\Models\BoardResult::query()
                ->whereIn('tenant_id', $schoolIds)
                ->where('academic_year', $year)
                ->whereHas('toppers')
                ->count(),
            'total_toppers' => \App\Models\Topper::query()
                ->join('board_results as br', 'br.id', '=', 'toppers.board_result_id')
                ->whereIn('br.tenant_id', $schoolIds)
                ->where('toppers.entry_type', \App\Models\Topper::ENTRY_OVERALL)
                ->where('br.academic_year', $year)
                ->count(),
            'subject_merit' => \App\Models\Topper::query()
                ->join('board_results as br', 'br.id', '=', 'toppers.board_result_id')
                ->whereIn('br.tenant_id', $schoolIds)
                ->where('toppers.entry_type', \App\Models\Topper::ENTRY_SUBJECT)
                ->where('br.academic_year', $year)
                ->count(),
            'schools_submitted' => \App\Models\BoardResult::query()
                ->whereIn('tenant_id', $schoolIds)
                ->where('academic_year', $year)
                ->whereIn('status', [\App\Models\BoardResult::STATUS_SUBMITTED, \App\Models\BoardResult::STATUS_VERIFIED, \App\Models\BoardResult::STATUS_APPROVED, \App\Models\BoardResult::STATUS_PUBLISHED])
                ->count(),
        ];

        return $this->inertia('Sahodaya/BoardResults/Toppers', [
            'selectedClass' => $class,
            'selectedStream' => $selectedStream,
            'selectedStreamLabel' => $selectedStreamLabel,
            'selectedSubjectId' => $selectedSubjectId,
            'filters' => ['academic_year' => $year, 'stream' => $selectedStream],
            'academicYearOptions' => app(\App\Services\BoardResults\BoardResultAcademicYearService::class)->activeOrPopulatedYearOptions((string) $this->sahodaya->id),
            'streamOptions' => $streams,
            'subjectOptions' => $subjects,
            'rows' => $streamRows,
            'counts' => $counts,
            'rankingSettings' => [
                'use_common_ranking' => (bool) $rankingSettings->use_common_ranking,
                'no_rank' => (bool) $rankingSettings->no_rank,
            ],
        ]);
    }

    /**
     * Standalone menu page linking out to the four topper-style reports (Class X Overall,
     * Class XII Stream-wise, Full A1 Achievers, Subject-Wise) for a single chosen academic
     * year — distinct from the general Reports hub, and from the Toppers settings hub above.
     */
    public function reportsMenu(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        return $this->inertia('Sahodaya/BoardResults/TopperReportsMenu', [
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => app(\App\Services\BoardResults\BoardResultAcademicYearService::class)->activeOrPopulatedYearOptions((string) $this->sahodaya->id),
        ]);
    }

    /**
     * Apply a one-off "view=rank|percentage" override (query string) on top of the persisted
     * no_rank setting, so a single report request can preview the other mode without saving.
     */
    private function applyViewOverride(Request $request): void
    {
        $view = $request->string('view')->toString();
        if (in_array($view, ['rank', 'percentage'], true)) {
            app(TopperCountService::class)->setNoRankOverride($this->sahodaya->id, $view === 'percentage');
        }
    }

    /** Overall Result — Sahodaya-wide toppers (Class X flat, Class XII per stream). */
    public function overall(Request $request, SahodayaTopperSelectionService $selection)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);

        $streams = $this->streamOptions();
        $selectedStream = $class === 12
            ? $this->resolveSelectedStreamCode($request, $streams)
            : null;
        $selectedStreamLabel = $selectedStream ? ($streams[$selectedStream] ?? null) : null;

        $overallRows = $class === 10 ? $selection->overallForClassX($this->sahodaya->id, $year) : [];
        $byStream = $class === 12 ? $selection->byStreamForClassXII($this->sahodaya->id, $year) : [];
        $streamRows = $class === 12 && $selectedStreamLabel
            ? ($byStream[$selectedStreamLabel] ?? [])
            : $overallRows;

        return $this->inertia('Sahodaya/BoardResults/TopperResultsOverall', [
            'selectedClass' => $class,
            'selectedStream' => $selectedStream,
            'selectedStreamLabel' => $selectedStreamLabel,
            'filters' => ['academic_year' => $year, 'stream' => $selectedStream],
            'academicYearOptions' => app(\App\Services\BoardResults\BoardResultAcademicYearService::class)->activeOrPopulatedYearOptions((string) $this->sahodaya->id),
            'streamOptions' => $streams,
            'overall' => $overallRows,
            'byStream' => $byStream,
            'rows' => $streamRows,
            'noRank' => app(TopperCountService::class)->isNoRankMode($this->sahodaya->id),
        ]);
    }

    /** Subject-Wise Top Scorers — Class XII only. */
    public function subjectWise(Request $request)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $subjectLeaders = app(\App\Services\BoardResults\SubjectMeritRegisterService::class)
            ->register($this->sahodaya->id, $year, 12);

        return $this->inertia('Sahodaya/BoardResults/TopperResultsSubjectWise', [
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => app(\App\Services\BoardResults\BoardResultAcademicYearService::class)->activeOrPopulatedYearOptions((string) $this->sahodaya->id),
            'subjectLeaders' => $subjectLeaders,
            'noRank' => app(TopperCountService::class)->isNoRankMode($this->sahodaya->id),
        ]);
    }

    /** 90%+ (or custom threshold) achievers — not capped to Top-N. */
    public function achievers(Request $request, SahodayaTopperSelectionService $selection)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);
        $threshold = $request->filled('threshold') ? (float) $request->input('threshold') : 90.0;

        $streams = $this->streamOptions();
        $selectedStream = $class === 12
            ? $this->resolveSelectedStreamCode($request, $streams)
            : null;
        $selectedStreamLabel = $selectedStream ? ($streams[$selectedStream] ?? null) : null;

        $achieversOverall = $class === 10 ? $selection->achieversForClassX($this->sahodaya->id, $year, $threshold) : [];
        $achieversByStream = $class === 12 ? $selection->achieversByStreamForClassXII($this->sahodaya->id, $year, $threshold) : [];
        $streamRows = $class === 12 && $selectedStreamLabel
            ? ($achieversByStream[$selectedStreamLabel] ?? [])
            : $achieversOverall;

        return $this->inertia('Sahodaya/BoardResults/TopperResultsAchievers', [
            'selectedClass' => $class,
            'selectedStream' => $selectedStream,
            'selectedStreamLabel' => $selectedStreamLabel,
            'filters' => ['academic_year' => $year, 'threshold' => rtrim(rtrim(number_format($threshold, 2), '0'), '.'), 'stream' => $selectedStream],
            'academicYearOptions' => app(\App\Services\BoardResults\BoardResultAcademicYearService::class)->activeOrPopulatedYearOptions((string) $this->sahodaya->id),
            'streamOptions' => $streams,
            'achieversOverall' => $achieversOverall,
            'achieversByStream' => $achieversByStream,
            'rows' => $streamRows,
            'noRank' => app(TopperCountService::class)->isNoRankMode($this->sahodaya->id),
        ]);
    }

    /** @return array<string, string> stream_code => label */
    private function streamOptions(): array
    {
        return ExamStream::labelsFor($this->sahodaya->id);
    }

    /** @return list<array{id:int, label:string}> */
    private function subjectOptions(): array
    {
        return Subject::query()
            ->forSahodaya($this->sahodaya->id)
            ->active()
            ->orderBy('label')
            ->get(['id', 'label'])
            ->map(fn (Subject $subject) => ['id' => (int) $subject->id, 'label' => $subject->label])
            ->values()
            ->all();
    }

    private function resolveSelectedStreamCode(Request $request, array $streams): ?string
    {
        $selected = $request->string('stream')->trim()->toString();
        if ($selected !== '' && array_key_exists($selected, $streams)) {
            return $selected;
        }

        return array_key_first($streams) ?: null;
    }

    private function resolveSelectedSubjectId(Request $request, array $subjects): ?int
    {
        if ($subjects === []) {
            return null;
        }

        $selected = $request->integer('subject_id') ?: null;
        if ($selected !== null && collect($subjects)->contains(fn (array $subject) => (int) $subject['id'] === $selected)) {
            return $selected;
        }

        return (int) ($subjects[0]['id'] ?? 0) ?: null;
    }

    public function recompute(Request $request, SahodayaTopperSelectionService $selection)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $result = $selection->recompute($this->sahodaya->id, $year);

        return back()->with('success', "Sahodaya toppers recomputed ({$result['rows']} rows).");
    }
}
