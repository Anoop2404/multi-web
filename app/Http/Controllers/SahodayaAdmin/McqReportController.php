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
            'registrations'=> $reports->registrationPreviewRows($exam),
            'feeSummary'   => $feeSummary,
            'classWiseCounts' => $reports->classWiseCountMatrix($exam),
            'resultAnalysis' => $exam->results_published ? $reports->resultAnalysis($exam) : null,
            'schoolPerformance' => $exam->results_published ? $reports->schoolPerformanceRows($exam) : [],
            'stats'        => [
                // Active = excludes cancelled registrations, matching the registration register/class-wise counts below.
                'registrations' => McqRegistration::where('exam_id', $exam->id)->active()->count(),
                'present'       => McqRegistration::where('exam_id', $exam->id)->active()->where('attendance_status', 'present')->count(),
                'malpractice'   => McqRegistration::where('exam_id', $exam->id)->active()->whereIn('attendance_status', ['malpractice', 'withheld'])->count(),
                'fee_collected' => collect($feeSummary)->where('status', 'approved')->sum('total_due'),
                'fee_pending'   => collect($feeSummary)->whereIn('status', ['proof_uploaded', 'pending'])->sum('total_due'),
            ],
            'schoolOptions' => $reports->schoolFilterOptions($exam),
            'classOptions'  => $reports->classFilterOptions($exam),
        ]);
    }

    /** Dedicated, filterable, paginated registration register page (photo roster export/preview live here too). */
    public function registrationRegister(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class'     => 'nullable|string|max:60',
        ]);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'teacher', 'school', 'mark', 'feeReceipt']);

        $this->applyRegistrationFilters($query, $filters);

        $registrations = $query->orderBy('hall_ticket_no')
            ->paginate(50)
            ->withQueryString();

        return $this->inertia('Sahodaya/Mcq/Reports/Registration', [
            'exam'          => $exam->only('id', 'title', 'exam_level', 'status'),
            'registrations' => $registrations,
            'filters'       => $filters,
            'schoolOptions' => $reports->schoolFilterOptions($exam),
            'classOptions'  => $reports->classFilterOptions($exam),
        ]);
    }

    /** Dedicated, filterable, paginated attendance sheet page (roster for exam-day marking, read-only). */
    public function attendanceSheetPage(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class'     => 'nullable|string|max:60',
        ]);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'teacher', 'school']);

        $this->applyRegistrationFilters($query, $filters);

        $registrations = $query->orderBy('hall_ticket_no')
            ->paginate(50)
            ->withQueryString();

        return $this->inertia('Sahodaya/Mcq/Reports/AttendanceSheet', [
            'exam'          => $exam->only('id', 'title', 'exam_level', 'status'),
            'registrations' => $registrations,
            'filters'       => $filters,
            'schoolOptions' => $reports->schoolFilterOptions($exam),
            'classOptions'  => $reports->classFilterOptions($exam),
        ]);
    }

    /** Dedicated, filterable fee summary page (bounded by school count, no pagination needed). */
    public function feeSummaryPage(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,proof_uploaded,partial,approved,rejected,waived',
        ]);

        $rows = collect($reports->feeSummaryRows($exam))
            ->when(filled($filters['search'] ?? null), fn ($c) => $c->filter(
                fn ($r) => str_contains(mb_strtolower($r['school_name'] ?? ''), mb_strtolower($filters['search']))
            ))
            ->when(filled($filters['status'] ?? null), fn ($c) => $c->where('status', $filters['status']))
            ->values()
            ->all();

        return $this->inertia('Sahodaya/Mcq/Reports/FeeSummary', [
            'exam'       => $exam->only('id', 'title', 'exam_level', 'status'),
            'feeSummary' => $rows,
            'filters'    => $filters,
        ]);
    }

    /** Dedicated, filterable class-wise registration counts page. */
    public function classWiseCountsPage(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'school_id' => 'nullable|string|max:36',
        ]);

        return $this->inertia('Sahodaya/Mcq/Reports/ClassWiseCounts', [
            'exam'            => $exam->only('id', 'title', 'exam_level', 'status'),
            'classWiseCounts' => $reports->classWiseCountMatrix($exam, $filters['school_id'] ?? null),
            'filters'         => $filters,
            'schoolOptions'   => $reports->schoolFilterOptions($exam),
        ]);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<McqRegistration>  $query */
    private function applyRegistrationFilters($query, array $filters): void
    {
        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        if (! empty($filters['class'])) {
            $class = mb_strtolower(trim((string) $filters['class']));
            $query->whereHas('student.schoolClass', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$class]));
        }

        if (! empty($filters['search'])) {
            $term = '%'.mb_strtolower(trim((string) $filters['search'])).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(hall_ticket_no) LIKE ?', [$term])
                    ->orWhereHas('student', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(admission_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(reg_no) LIKE ?', [$term]))
                    ->orWhereHas('teacher', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(employee_code) LIKE ?', [$term]))
                    ->orWhereHas('school', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }
    }

    public function exportClassWiseCounts(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportClassWiseCounts($exam, $request->input('school_id'));
    }

    public function exportClassWiseCountsPdf(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $printable->classWiseCountsPdf(
            $exam,
            schoolId: $request->input('school_id'),
            inline: $request->boolean('inline') || $request->query('preview') == '1',
            sahodaya: $this->sahodaya,
        );
    }

    public function exportRegistration(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportRegistrationRegister($exam, $request->input('school_id'), $request->input('class'));
    }

    public function exportRegistrationPdf(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $printable)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $printable->classWiseRegistrationPdf(
            $exam,
            schoolId: $request->input('school_id'),
            selectedClass: $request->input('class'),
            inline: $request->boolean('inline') || $request->query('preview') == '1',
            sahodaya: $this->sahodaya,
        );
    }

    public function exportFees(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportFeeSummary($exam);
    }

    public function exportAttendance(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($exam, $request->input('school_id'), $request->input('class'));
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

    public function exportAbsent(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAbsentList($exam, $request->input('school_id'));
    }

    public function exportMarksPending(string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportMarksPending($exam);
    }

    public function exportMarksEntryTemplate(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportMarksEntryTemplate($exam, $request->input('school_id'), $request->input('class'));
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

    public function exportMalpractice(Request $request, string $tenantId, McqExam $exam, McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportMalpracticeList($exam, $request->input('school_id'));
    }
}
