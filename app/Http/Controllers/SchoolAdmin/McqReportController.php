<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Services\Mcq\McqPrintableDocumentService;
use App\Services\Mcq\McqReportService;
use Illuminate\Http\Request;

class McqReportController extends SchoolAdminController
{
    public function exportRegistration(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $reports->exportRegistrationRegister($exam, $this->school->id, $selectedClass);
    }

    public function exportRegistrationPdf(Request $request, string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $inline = $request->boolean('inline') || $request->query('inline') == '1' || $request->query('preview') == '1';
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $printable->classWiseRegistrationPdf($exam, $this->school->id, $selectedClass, $inline);
    }

    public function exportAttendance(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $reports->exportAttendance($exam, $this->school->id, $selectedClass);
    }

    public function exportAttendancePdf(Request $request, string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $inline = $request->boolean('inline') || $request->query('inline') == '1' || $request->query('preview') == '1';
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $printable->attendanceSheetPdf($exam, $this->school->id, $selectedClass, $inline);
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

    public function exportClassWiseCountsPdf(Request $request, string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $inline = $request->boolean('inline') || $request->query('inline') == '1' || $request->query('preview') == '1';

        return $printable->classWiseCountsPdf($exam, $this->school->id, $inline);
    }

    public function exportClassWiseFeeDue(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $reports->exportClassWiseFeeDue($exam, $this->school->id, $selectedClass);
    }

    public function exportClassWiseFeeDuePdf(Request $request, string $tenantId, McqExam $exam, McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        $inline = $request->boolean('inline') || $request->query('inline') == '1' || $request->query('preview') == '1';
        $selectedClass = $request->input('class') ?: $request->input('class_name');

        return $printable->classWiseFeeDuePdf($exam, $this->school->id, $selectedClass, $inline);
    }
}
