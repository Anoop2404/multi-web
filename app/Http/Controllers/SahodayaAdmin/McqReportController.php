<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Mcq\McqReportService;
use Illuminate\Http\Request;

class McqReportController extends SahodayaAdminController
{
    public function show(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $feeSummary = $reports->feeSummaryRows($exam);

        return $this->inertia('Sahodaya/Mcq/Reports', [
            'exam'         => $exam->only('id', 'title', 'exam_level', 'status', 'results_published', 'delivery_mode'),
            'registrations'=> $reports->registrationRows($exam),
            'feeSummary'   => $feeSummary,
            'resultAnalysis' => $exam->results_published ? $reports->resultAnalysis($exam) : null,
            'schoolPerformance' => $exam->results_published ? $reports->schoolPerformanceRows($exam) : [],
            'stats'        => [
                'registrations' => McqRegistration::where('exam_id', $exam->id)->count(),
                'present'       => McqRegistration::where('exam_id', $exam->id)->where('attendance_status', 'present')->count(),
                'malpractice'   => McqRegistration::where('exam_id', $exam->id)->whereIn('attendance_status', ['malpractice', 'withheld'])->count(),
                'fee_collected' => collect($feeSummary)->where('status', 'approved')->sum('total_due'),
                'fee_pending'   => collect($feeSummary)->whereIn('status', ['proof_uploaded', 'pending'])->sum('total_due'),
            ],
        ]);
    }

    public function exportRegistration(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportRegistrationRegister($exam);
    }

    public function exportFees(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportFeeSummary($exam);
    }

    public function exportAttendance(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($exam);
    }

    public function exportToppers(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($exam->results_published, 422, 'Results are not published yet.');

        return $reports->exportToppers($exam);
    }

    public function exportLevel2Qualifiers(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportLevel2Qualifiers($exam);
    }

    public function exportAbsent(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAbsentList($exam);
    }

    public function exportMarksPending(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportMarksPending($exam);
    }

    public function exportPendingFees(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportPendingFees($exam);
    }

    public function exportRejectedFees(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportRejectedFees($exam);
    }

    public function exportGradeBands(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportGradeBands($exam);
    }

    public function exportSessionStatus(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportSessionStatus($exam);
    }

    public function exportResultAnalysis(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($exam->results_published, 422, 'Results are not published yet.');

        return $reports->exportResultAnalysis($exam);
    }

    public function exportSchoolPerformance(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($exam->results_published, 422, 'Results are not published yet.');

        return $reports->exportSchoolPerformance($exam);
    }

    public function exportMalpractice(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportMalpracticeList($exam);
    }
}
