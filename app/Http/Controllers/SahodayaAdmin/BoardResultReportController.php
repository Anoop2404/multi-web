<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Subject;
use App\Services\BoardResults\AcademicExcellenceReportService;
use App\Services\BoardResults\FullA1AchieversReportService;
use App\Services\BoardResults\SahodayaTopperSelectionService;
use App\Services\BoardResults\SubjectMeritRegisterService;
use App\Services\BoardResults\TopperCountService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

class BoardResultReportController extends SahodayaAdminController
{
    /**
     * Apply a one-off "view=rank|percentage" override (query string) on top of the persisted
     * no_rank setting, so a single PDF download can match whatever mode the report page was
     * showing at the time, without touching the saved sahodaya-wide setting.
     */
    private function applyViewOverride(Request $request): void
    {
        $view = $request->string('view')->toString();
        if (in_array($view, ['rank', 'percentage'], true)) {
            app(TopperCountService::class)->setNoRankOverride($this->sahodaya->id, $view === 'percentage');
        }
    }

    public function index(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $base = "/sahodaya-admin/{$this->sahodaya->id}";
        $yearQ = urlencode($year);

        $availableYears = AcademicYear::options();

        return $this->inertia('Sahodaya/BoardResults/Reports', [
            'filters' => [
                'academic_year' => $year,
                'class' => $request->integer('class') ?: null,
            ],
            'availableYears' => $availableYears,
            'reports' => [
                [
                    'key' => 'RPT-BRD-001-X10',
                    'title' => 'School Result Summary — Class X',
                    'description' => 'Appeared, passed, pass %, distinctions, highest mark by school (AISSE).',
                    'href' => "{$base}/reports/RPT-BRD-001?academic_year={$yearQ}&class=10",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-001/export?academic_year={$yearQ}&class=10&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-001/export?academic_year={$yearQ}&class=10&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-001-X12',
                    'title' => 'School Result Summary — Class XII',
                    'description' => 'Appeared, passed, pass %, distinctions, highest mark by school (AISSCE).',
                    'href' => "{$base}/reports/RPT-BRD-001?academic_year={$yearQ}&class=12",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-001/export?academic_year={$yearQ}&class=12&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-001/export?academic_year={$yearQ}&class=12&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-002-X10',
                    'title' => 'Overall Ranking — Class X',
                    'description' => 'Sahodaya-wide school rankings from the Ranking Engine (AISSE).',
                    'href' => "{$base}/reports/RPT-BRD-002?academic_year={$yearQ}&class=10",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-002/export?academic_year={$yearQ}&class=10&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-002/export?academic_year={$yearQ}&class=10&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-002-X12',
                    'title' => 'Overall Ranking — Class XII',
                    'description' => 'Sahodaya-wide school rankings from the Ranking Engine (AISSCE).',
                    'href' => "{$base}/reports/RPT-BRD-002?academic_year={$yearQ}&class=12",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-002/export?academic_year={$yearQ}&class=12&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-002/export?academic_year={$yearQ}&class=12&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-003-X10',
                    'title' => 'Pass % Report — Class X',
                    'description' => 'Approved/published Class X results ordered by pass percentage.',
                    'href' => "{$base}/reports/RPT-BRD-003?academic_year={$yearQ}&class=10",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-003/export?academic_year={$yearQ}&class=10&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-003/export?academic_year={$yearQ}&class=10&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-003-X12',
                    'title' => 'Pass % Report — Class XII',
                    'description' => 'Approved/published Class XII results ordered by pass percentage.',
                    'href' => "{$base}/reports/RPT-BRD-003?academic_year={$yearQ}&class=12",
                    'pdfUrl' => "{$base}/reports/RPT-BRD-003/export?academic_year={$yearQ}&class=12&format=pdf",
                    'excelUrl' => "{$base}/reports/RPT-BRD-003/export?academic_year={$yearQ}&class=12&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-004',
                    'title' => 'Class X Merit Register',
                    'description' => 'AISSE toppers across member schools.',
                    'href' => "{$base}/reports/RPT-BRD-004?academic_year={$yearQ}",
                    'pdfUrl' => "{$base}/board-results/reports/toppers/pdf?academic_year={$yearQ}&class=10",
                    'excelUrl' => "{$base}/reports/RPT-BRD-004/export?academic_year={$yearQ}&format=xlsx",
                ],
                [
                    'key' => 'RPT-BRD-005',
                    'title' => 'Stream Merit Register — Class XII',
                    'description' => 'AISSCE toppers by stream.',
                    'href' => "{$base}/reports/RPT-BRD-005?academic_year={$yearQ}",
                    'pdfUrl' => "{$base}/board-results/reports/toppers/pdf?academic_year={$yearQ}&class=12",
                    'excelUrl' => "{$base}/reports/RPT-BRD-005/export?academic_year={$yearQ}&format=xlsx",
                ],
                [
                    'key' => 'subject-merit-x10',
                    'title' => 'Subject-wise Merit Register — Class X',
                    'description' => 'Highest scorers per subject from normalized topper marks (AISSE).',
                    'href' => "{$base}/board-results/reports/subject-merit?academic_year={$yearQ}&class=10",
                    'pdfUrl' => "{$base}/board-results/reports/subject-merit/pdf?academic_year={$yearQ}&class=10",
                    'excelUrl' => "{$base}/reports/RPT-BRD-004/export?academic_year={$yearQ}&class=10&format=xlsx",
                ],
                [
                    'key' => 'subject-merit-x12',
                    'title' => 'Subject-wise Merit Register — Class XII',
                    'description' => 'Highest scorers per subject from normalized topper marks (AISSCE).',
                    'href' => "{$base}/board-results/reports/subject-merit?academic_year={$yearQ}&class=12",
                    'pdfUrl' => "{$base}/board-results/reports/subject-merit/pdf?academic_year={$yearQ}&class=12",
                    'excelUrl' => "{$base}/reports/RPT-BRD-005/export?academic_year={$yearQ}&class=12&format=xlsx",
                ],
                [
                    'key' => 'full-a1-achievers',
                    'title' => 'Full A1 Achievers — Class X & XII',
                    'description' => 'Students who scored A1 (91-100) in every subject entered, all streams.',
                    'href' => "{$base}/board-results/reports/full-a1-achievers?academic_year={$yearQ}",
                    'pdfUrl' => "{$base}/board-results/reports/full-a1-achievers/pdf?academic_year={$yearQ}",
                    'excelUrl' => "{$base}/reports/RPT-BRD-004/export?academic_year={$yearQ}&format=xlsx",
                ],
                [
                    'key' => 'excellence',
                    'title' => 'Academic Excellence + Historical Comparison',
                    'description' => 'Awards and year-over-year pass % trends (all classes).',
                    'href' => "{$base}/board-results/reports/excellence?academic_year={$yearQ}",
                    'pdfUrl' => "{$base}/board-results/reports/toppers/pdf?academic_year={$yearQ}",
                    'excelUrl' => "{$base}/reports/RPT-BRD-001/export?academic_year={$yearQ}&format=xlsx",
                ],
            ],
        ]);
    }

    public function subjectMerit(Request $request, SubjectMeritRegisterService $service)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->filled('class') ? $request->integer('class') : null;

        $rows = $service->register($this->sahodaya->id, $year, $class);

        $schoolOptions = \App\Models\Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values()
            ->all();

        $subjectOptions = Subject::query()
            ->forSahodaya($this->sahodaya->id)
            ->active()
            ->orderBy('label')
            ->get(['id', 'label'])
            ->map(fn ($subject) => ['id' => $subject->id, 'label' => $subject->label])
            ->values()
            ->all();

        // AcademicYear::optionsForSahodaya() never existed — the Vue page expects
        // {id, label} pairs for its <option :key="ay.id" :value="ay.label"> loop, not the
        // flat label strings AcademicYear::options() returns. Matches the same query
        // FestEventController already uses for the same shape (see its 'academicYearOptions').
        $academicYearOptions = \App\Models\AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label']);

        return $this->inertia('Sahodaya/BoardResults/SubjectMeritRegister', [
            'rows' => $rows,
            'filters' => [
                'academic_year' => $year,
                'class' => $class,
            ],
            'classOptions' => [10, 12],
            'schoolOptions' => $schoolOptions,
            'subjectOptions' => $subjectOptions,
            'academicYearOptions' => $academicYearOptions,
        ]);
    }

    public function fullA1Achievers(Request $request, FullA1AchieversReportService $service)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->filled('class') ? $request->integer('class') : null;
        $stream = $request->filled('stream') ? $request->string('stream')->toString() : null;

        $rows = $service->list($this->sahodaya->id, $year, $class, $stream);

        $academicYearOptions = \App\Models\AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label']);

        return $this->inertia('Sahodaya/BoardResults/FullA1Achievers', [
            'rows' => $rows,
            'filters' => [
                'academic_year' => $year,
                'class' => $class,
                'stream' => $stream,
            ],
            'classOptions' => [10, 12],
            'streamOptions' => ['Science', 'Commerce', 'Humanities'],
            'academicYearOptions' => $academicYearOptions,
        ]);
    }

    public function excellence(Request $request, AcademicExcellenceReportService $service)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        return $this->inertia('Sahodaya/BoardResults/ExcellenceReport', [
            'report' => $service->report($this->sahodaya->id, $year),
            'filters' => ['academic_year' => $year],
        ]);
    }

    public function subjectMeritPdf(Request $request, SubjectMeritRegisterService $service, TopperCountService $counts)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->filled('class') ? $request->integer('class') : null;

        $rows = $service->register($this->sahodaya->id, $year, $class);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.subject-merit', [
            'rows'          => $rows,
            'academicYear'  => $year,
            'selectedClass' => $class,
            'orgName'       => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'       => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'generatedAt'   => now()->format('d M Y · h:i A'),
            'noRank'        => $counts->isNoRankMode($this->sahodaya->id),
        ])->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download("subject-merit-register-{$year}.pdf");
        }

        return $pdf->stream("subject-merit-register-{$year}.pdf");
    }

    public function fullA1AchieversPdf(Request $request, FullA1AchieversReportService $service)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->filled('class') ? $request->integer('class') : null;
        $stream = $request->filled('stream') ? $request->string('stream')->toString() : null;

        $rows = $service->list($this->sahodaya->id, $year, $class, $stream);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.full-a1-achievers', [
            'rows'           => $rows,
            'academicYear'   => $year,
            'selectedClass'  => $class,
            'selectedStream' => $stream,
            'orgName'        => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'        => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'generatedAt'    => now()->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download("full-a1-achievers-{$year}.pdf");
        }

        return $pdf->stream("full-a1-achievers-{$year}.pdf");
    }

    public function toppersPdf(Request $request, SahodayaTopperSelectionService $topperService, TopperCountService $counts)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $classXToppers = $topperService->overallForClassX($this->sahodaya->id, $year);
        $classXIIToppers = $topperService->byStreamForClassXII($this->sahodaya->id, $year);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.overall-toppers', [
            'classXToppers'   => $classXToppers,
            'classXIIToppers' => $classXIIToppers,
            'academicYear'    => $year,
            'orgName'         => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'         => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'generatedAt'     => now()->format('d M Y · h:i A'),
            'noRank'          => $counts->isNoRankMode($this->sahodaya->id),
        ])->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download("board-results-toppers-{$year}.pdf");
        }

        return $pdf->stream("board-results-toppers-{$year}.pdf");
    }

    /** PDF export for the Toppers-hub "90%+ Achievers" report (no existing PDF previously). */
    public function achieversPdf(Request $request, SahodayaTopperSelectionService $selection, TopperCountService $counts)
    {
        $this->applyViewOverride($request);

        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $class = $request->integer('class') ?: 10;
        abort_unless(in_array($class, [10, 12], true), 404);
        $threshold = $request->filled('threshold') ? (float) $request->input('threshold') : 90.0;

        $overall = $class === 10 ? $selection->achieversForClassX($this->sahodaya->id, $year, $threshold) : [];
        $byStream = $class === 12 ? $selection->achieversByStreamForClassXII($this->sahodaya->id, $year, $threshold) : [];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('board-results.pdf.topper-achievers', [
            'overall'       => $overall,
            'byStream'      => $byStream,
            'selectedClass' => $class,
            'threshold'     => $threshold,
            'academicYear'  => $year,
            'orgName'       => $this->sahodaya->name ?? 'Sahodaya',
            'logoSrc'       => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'generatedAt'   => now()->format('d M Y · h:i A'),
            'noRank'        => $counts->isNoRankMode($this->sahodaya->id),
        ])->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download("achievers-class-{$class}-{$year}.pdf");
        }

        return $pdf->stream("achievers-class-{$class}-{$year}.pdf");
    }
}
