<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Services\Mcq\McqReportService;

class McqReportController extends SchoolAdminController
{
    public function exportRegistration(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportRegistrationRegister($exam, $this->school->id);
    }

    public function exportAttendance(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        return $reports->exportAttendance($exam, $this->school->id);
    }

    public function exportToppers(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_unless($exam->results_published, 422, 'Results are not published yet.');

        return $reports->exportToppers($exam, $this->school->id);
    }
}
