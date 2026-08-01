<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Services\Mcq\McqPrintableDocumentService;
use App\Services\Mcq\McqReportService;

class McqReportController extends SchoolAdminController
{
    public function exportRegistration(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportRegistrationRegister($exam, $this->school->id);
    }

    public function exportRegistrationPdf(string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $printable->classWiseRegistrationPdf($exam, $this->school->id);
    }

    public function exportAttendance(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportAttendance($exam, $this->school->id);
    }

    public function exportAttendancePdf(string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $printable->attendanceSheetPdf($exam, $this->school->id);
    }

    public function exportToppers(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless($exam->results_published, 422, 'Results are not published yet.');

        return $reports->exportToppers($exam, $this->school->id);
    }

    public function exportClassWiseCounts(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportClassWiseCounts($exam, $this->school->id);
    }

    public function exportClassWiseCountsPdf(string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $printable->classWiseCountsPdf($exam, $this->school->id);
    }

    public function exportClassWiseFeeDue(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportClassWiseFeeDue($exam, $this->school->id);
    }

    public function exportClassWiseFeeDuePdf(string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $printable->classWiseFeeDuePdf($exam, $this->school->id);
    }
}
