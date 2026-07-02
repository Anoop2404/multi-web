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
            'exam'         => $exam->only('id', 'title', 'exam_level', 'status', 'results_published'),
            'registrations'=> $reports->registrationRows($exam),
            'feeSummary'   => $feeSummary,
            'stats'        => [
                'registrations' => McqRegistration::where('exam_id', $exam->id)->count(),
                'present'       => McqRegistration::where('exam_id', $exam->id)->where('attendance_status', 'present')->count(),
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
}
