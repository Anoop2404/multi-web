<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\ExamStream;
use App\Models\Subject;
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

        $streams = $this->streamOptions();
        $subjects = $this->subjectOptions();
        $selectedStream = $class === 12
            ? $this->resolveSelectedStreamCode($request, $streams)
            : null;
        $selectedSubjectId = $this->resolveSelectedSubjectId($request, $subjects);

        [$overallConfig, $streamConfigs, $subjectConfigs] = $this->loadConfigs($streams, $subjects, $class);

        return $this->inertia('Sahodaya/BoardResults/Toppers', [
            'selectedClass' => $class,
            'selectedStream' => $selectedStream,
            'selectedSubjectId' => $selectedSubjectId,
            'filters' => ['academic_year' => $year],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'streamOptions' => $streams,
            'subjectOptions' => $subjects,
            'settings' => [
                'overall' => [
                    'top_n' => $overallConfig?->top_n ?? TopperCountService::DEFAULT_TOP_N,
                    'tie_mode' => $overallConfig?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP,
                    'rank_style' => $overallConfig?->rank_style ?? TopperCountConfig::RANK_COMPETITION,
                ],
                'streams' => $streamConfigs,
                'subjects' => $subjectConfigs,
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
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'streamOptions' => $streams,
            'overall' => $overallRows,
            'byStream' => $byStream,
            'rows' => $streamRows,
        ]);
    }

    /** Subject-Wise Top Scorers — Class XII only. */
    public function subjectWise(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $streams = $this->streamOptions();
        $selectedStream = $this->resolveSelectedStreamCode($request, $streams);
        $selectedStreamLabel = $selectedStream ? ($streams[$selectedStream] ?? null) : null;

        $subjectLeaders = app(\App\Services\BoardResults\SubjectMeritRegisterService::class)
            ->register($this->sahodaya->id, $year, 12);

        if ($selectedStreamLabel) {
            $subjectLeaders = array_values(array_filter($subjectLeaders, fn ($row) => ($row['stream'] ?? null) === $selectedStreamLabel));
        }

        return $this->inertia('Sahodaya/BoardResults/TopperResultsSubjectWise', [
            'filters' => ['academic_year' => $year, 'stream' => $selectedStream],
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'streamOptions' => $streams,
            'selectedStream' => $selectedStream,
            'selectedStreamLabel' => $selectedStreamLabel,
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
            'academicYearOptions' => AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'streamOptions' => $streams,
            'achieversOverall' => $achieversOverall,
            'achieversByStream' => $achieversByStream,
            'rows' => $streamRows,
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

    /** @param  list<array{id:int, label:string}>  $subjects
     *  @return array{0: ?TopperCountConfig, 1: array<string, array{stream_id:int, stream_code:string, stream_label:string, top_n:int, tie_mode:string, rank_style:string}>, 2: array<string, array{subject_id:int, subject_label:string, top_n:int, tie_mode:string, rank_style:string}>}
     */
    private function loadConfigs(array $streams, array $subjects, int $class): array
    {
        $overallConfig = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_OVERALL)
            ->where(function ($q) use ($class) { $q->where('class', $class)->orWhereNull('class'); })
            ->first();

        $streamIds = ExamStream::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->whereIn('code', array_keys($streams))
            ->pluck('id', 'code');

        $defaultStreamConfig = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_STREAM)
            ->where(function ($q) use ($class) { $q->where('class', $class)->orWhereNull('class'); })
            ->whereNull('stream_id')
            ->first();
        $streamConfigsById = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_STREAM)
            ->where(function ($q) use ($class) { $q->where('class', $class)->orWhereNull('class'); })
            ->get()
            ->keyBy(fn (TopperCountConfig $config) => (int) $config->stream_id);

        $defaultSubjectConfig = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_SUBJECT)
            ->where(function ($q) use ($class) { $q->where('class', $class)->orWhereNull('class'); })
            ->whereNull('subject_id')
            ->first();
        $subjectConfigsById = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('scope', TopperCountConfig::SCOPE_SUBJECT)
            ->where(function ($q) use ($class) { $q->where('class', $class)->orWhereNull('class'); })
            ->get()
            ->keyBy(fn (TopperCountConfig $config) => (int) $config->subject_id);

        $mapped = [];
        foreach ($streams as $code => $label) {
            $streamId = (int) ($streamIds[$code] ?? 0);
            $config = $streamId ? ($streamConfigsById->get($streamId) ?: $defaultStreamConfig) : $defaultStreamConfig;
            $mapped[$code] = [
                'stream_id' => $streamId,
                'stream_code' => $code,
                'stream_label' => $label,
                'top_n' => $config?->top_n ?? TopperCountService::DEFAULT_TOP_N,
                'tie_mode' => $config?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP,
                'rank_style' => $config?->rank_style ?? TopperCountConfig::RANK_COMPETITION,
            ];
        }

        $subjectMapped = [];
        foreach ($subjects as $subject) {
            $subjectId = (int) $subject['id'];
            $config = $subjectConfigsById->get($subjectId) ?: $defaultSubjectConfig;
            $subjectMapped[(string) $subjectId] = [
                'subject_id' => $subjectId,
                'subject_label' => $subject['label'],
                'top_n' => $config?->top_n ?? TopperCountService::DEFAULT_TOP_N,
                'tie_mode' => $config?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP,
                'rank_style' => $config?->rank_style ?? TopperCountConfig::RANK_COMPETITION,
            ];
        }

        return [$overallConfig, $mapped, $subjectMapped];
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
