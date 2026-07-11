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

        return $this->inertia('Sahodaya/BoardResults/Reports', [
            'filters' => [
                'academic_year' => $year,
                'class' => $request->integer('class') ?: null,
            ],
            'reports' => [
                [
                    'key' => 'subject-merit',
                    'title' => 'Subject-wise Merit Register',
                    'href' => "/sahodaya-admin/{$this->sahodaya->id}/board-results/reports/subject-merit?academic_year={$year}",
                ],
                [
                    'key' => 'excellence',
                    'title' => 'Academic Excellence + Historical Comparison',
                    'href' => "/sahodaya-admin/{$this->sahodaya->id}/board-results/reports/excellence?academic_year={$year}",
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
