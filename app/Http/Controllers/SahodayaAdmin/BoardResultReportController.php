<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Services\BoardResults\AcademicExcellenceReportService;
use App\Services\BoardResults\SubjectMeritRegisterService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

class BoardResultReportController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $year = $request->string('academic_year')->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);
        $base = "/sahodaya-admin/{$this->sahodaya->id}";
        $yearQ = urlencode($year);

        return $this->inertia('Sahodaya/BoardResults/Reports', [
            'filters' => [
                'academic_year' => $year,
                'class' => $request->integer('class') ?: null,
            ],
            'reports' => [
                [
                    'key' => 'RPT-BRD-001',
                    'title' => 'School Result Summary',
                    'description' => 'Appeared, passed, pass %, distinctions, highest mark by school.',
                    'href' => "{$base}/reports/RPT-BRD-001?academic_year={$yearQ}",
                ],
                [
                    'key' => 'RPT-BRD-002',
                    'title' => 'Overall Ranking',
                    'description' => 'Sahodaya-wide school rankings from the Ranking Engine.',
                    'href' => "{$base}/reports/RPT-BRD-002?academic_year={$yearQ}",
                ],
                [
                    'key' => 'RPT-BRD-003',
                    'title' => 'Pass % Report',
                    'description' => 'Approved/published results ordered by pass percentage.',
                    'href' => "{$base}/reports/RPT-BRD-003?academic_year={$yearQ}",
                ],
                [
                    'key' => 'RPT-BRD-004',
                    'title' => 'Class X Merit Register',
                    'description' => 'AISSE toppers across member schools.',
                    'href' => "{$base}/reports/RPT-BRD-004?academic_year={$yearQ}",
                ],
                [
                    'key' => 'RPT-BRD-005',
                    'title' => 'Stream Merit Register',
                    'description' => 'AISSCE toppers by stream.',
                    'href' => "{$base}/reports/RPT-BRD-005?academic_year={$yearQ}",
                ],
                [
                    'key' => 'subject-merit',
                    'title' => 'Subject-wise Merit Register',
                    'description' => 'Highest scorers per subject from normalized topper marks.',
                    'href' => "{$base}/board-results/reports/subject-merit?academic_year={$yearQ}",
                ],
                [
                    'key' => 'excellence',
                    'title' => 'Academic Excellence + Historical Comparison',
                    'description' => 'Awards and year-over-year pass % trends.',
                    'href' => "{$base}/board-results/reports/excellence?academic_year={$yearQ}",
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

        return $this->inertia('Sahodaya/BoardResults/SubjectMeritRegister', [
            'rows' => $rows,
            'filters' => [
                'academic_year' => $year,
                'class' => $class,
            ],
            'classOptions' => [10, 12],
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
}
