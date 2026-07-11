<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\User;
use App\Services\Mcq\McqHallTicketService;
use App\Support\Mcq\McqExamPayload;
use App\Support\Mcq\McqHallTicketDesign;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class McqExamOpsController extends SahodayaAdminController
{
    public function attendance(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->get();

        $summary = [
            'total'        => $registrations->count(),
            'pending'      => $registrations->whereNull('attendance_status')->count()
                + $registrations->where('attendance_status', 'pending')->count(),
            'present'      => $registrations->where('attendance_status', 'present')->count(),
            'absent'       => $registrations->where('attendance_status', 'absent')->count(),
            'malpractice'  => $registrations->where('attendance_status', 'malpractice')->count(),
            'withheld'     => $registrations->where('attendance_status', 'withheld')->count(),
            'marks_entered'=> $registrations->filter(fn ($r) => $r->mark !== null)->count(),
            'not_marked'   => $registrations->where('attendance_status', 'present')->filter(fn ($r) => $r->mark === null)->count(),
        ];

        $pendingCorrectionsCount = \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
            ->where('status', 'pending')
            ->count();

        return $this->inertia('Sahodaya/Mcq/Attendance', compact('exam', 'registrations', 'summary', 'pendingCorrectionsCount'));
    }

    public function storeAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_id'   => 'required|exists:mcq_registrations,id',
            'attendance_status' => 'required|in:present,absent,malpractice,withheld',
            'attendance_note'   => 'nullable|required_if:attendance_status,malpractice,withheld|string|max:1000',
        ]);

        $registration = McqRegistration::where('exam_id', $exam->id)->findOrFail($data['registration_id']);
        $registration->update([
            'attendance_status'    => $data['attendance_status'],
            'attendance_note'      => $data['attendance_note'] ?? null,
            'attendance_marked_at' => now(),
            'attendance_marked_by' => $request->user()->id,
        ]);

        if ($registration->blocksScoring() && $registration->mark) {
            $registration->mark()->delete();
            $registration->update(['status' => 'registered', 'submitted_at' => null]);
        }

        return back()->with('success', 'Attendance saved.');
    }

    public function importAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $result = app(\App\Services\Mcq\McqMarksAttendanceImporter::class)
            ->importAttendance($request->file('file'), $exam, $request->user()->id);

        if ($result['errors'] !== []) {
            $preview = collect($result['errors'])->take(8)->map(fn ($e) => "Row {$e['row']}: {$e['message']}")->implode(' · ');

            return back()->with([
                'success' => $result['imported'] > 0
                    ? "Imported attendance for {$result['imported']} registration(s)."
                    : null,
                'import_errors' => $result['errors'],
                'warning' => $preview !== '' ? "Import issues: {$preview}" : null,
            ]);
        }

        return back()->with('success', "Imported attendance for {$result['imported']} registration(s).");
    }

    public function exportAttendance(string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($exam);
    }

    public function attendanceSheetPdf(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->attendanceSheetPdf($exam, $this->sahodaya);
    }

    public function markSheetPdf(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->markSheetPdf($exam, $this->sahodaya);
    }

    public function resultSheetPdf(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->resultSheetPdf($exam, $this->sahodaya);
    }

    public function registrationInvoice(string $tenantId, McqExam $exam, McqRegistration $registration, \App\Services\Mcq\McqRegistrationInvoiceService $invoices)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 404);

        return $invoices->download($registration, $this->sahodaya);
    }

    public function results(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['mark', 'student', 'school', 'feeReceipt'])
            ->orderBy('hall_ticket_no')
            ->get();

        $gradeBands = app(\App\Services\Mcq\McqGradeService::class)->bandsForExam($exam);

        return $this->inertia('Sahodaya/Mcq/Results', compact('exam', 'registrations', 'gradeBands'));
    }

    public function hallTickets(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'teacher', 'school'])
            ->orderBy('hall_ticket_no')
            ->get();

        $design = McqHallTicketDesign::fromExam($exam);
        $logoUrl = McqHallTicketDesign::logoUrl($this->sahodaya, $design);
        $ticketsIssued = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();
        $seating = app(\App\Services\Mcq\McqSeatingService::class);

        return $this->inertia('Sahodaya/Mcq/HallTickets', [
            'exam'            => $exam,
            'registrations'   => $registrations,
            'hallTicketDesign'=> $design,
            'logoUrl'         => $logoUrl,
            'previewSample'   => McqHallTicketDesign::previewSample($exam),
            'ticketsIssued'   => $ticketsIssued,
            'halls'           => $seating->normalizedHalls($exam),
        ]);
    }

    public function previewHallTicket(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $design = McqHallTicketDesign::normalize(
            array_merge(
                McqHallTicketDesign::fromExam($exam),
                array_filter([
                    'header_title'  => request('header_title'),
                    'footer_note'   => request('footer_note'),
                    'primary_color' => request('primary_color'),
                    'accent_color'  => request('accent_color'),
                    'layout'        => request('layout'),
                    'show_reg_no'   => request()->has('show_reg_no') ? request()->boolean('show_reg_no') : null,
                    'show_school'   => request()->has('show_school') ? request()->boolean('show_school') : null,
                ], fn ($v) => $v !== null),
            ),
        );

        return view('mcq.hall-ticket-preview', [
            'design'  => $design,
            'logoUrl' => McqHallTicketDesign::logoUrl($this->sahodaya, $design),
            'sample'  => McqHallTicketDesign::previewSample($exam),
        ]);
    }

    public function updateHallTicketDesign(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'header_title'        => 'nullable|string|max:120',
            'footer_note'         => 'nullable|string|max:500',
            'show_reg_no'         => 'nullable|boolean',
            'show_school'         => 'nullable|boolean',
            'primary_color'       => 'nullable|string|max:7',
            'accent_color'        => 'nullable|string|max:7',
            'layout'              => 'nullable|in:standard,compact',
            'next_hall_ticket_no' => 'nullable|integer|min:1|max:99999999',
            'hall_instructions'   => 'nullable|string|max:2000',
            'logo'                => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'remove_logo'         => 'nullable|boolean',
        ]);

        if (array_key_exists('next_hall_ticket_no', $data) && $data['next_hall_ticket_no'] !== null) {
            $data['next_hall_ticket_no'] = McqExamPayload::nextHallTicketNo($data['next_hall_ticket_no']);
            $hasTickets = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();
            abort_if($hasTickets, 422, 'Starting reg. no. cannot be changed after hall tickets are issued.');
        }

        $current = McqHallTicketDesign::fromExam($exam);

        if ($request->boolean('remove_logo')) {
            $current['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $current['logo_path'] = TenantStorage::storeUploadedFile(
                $request->file('logo'),
                "mcq-hall-tickets/{$this->sahodaya->id}/{$exam->id}",
            );
        }

        $design = McqHallTicketDesign::normalize(array_merge($current, [
            'header_title'  => $data['header_title'] ?? $current['header_title'],
            'footer_note'   => $data['footer_note'] ?? $current['footer_note'],
            'show_reg_no'   => array_key_exists('show_reg_no', $data) ? (bool) $data['show_reg_no'] : $current['show_reg_no'],
            'show_school'   => array_key_exists('show_school', $data) ? (bool) $data['show_school'] : $current['show_school'],
            'primary_color' => $data['primary_color'] ?? $current['primary_color'],
            'accent_color'  => $data['accent_color'] ?? $current['accent_color'],
            'layout'        => $data['layout'] ?? $current['layout'],
        ]));

        $settings = McqHallTicketDesign::mergeIntoSettings($exam->settings_json ?? [], $design);

        $exam->update([
            'settings_json'       => $settings,
            'hall_instructions'   => $data['hall_instructions'] ?? $exam->hall_instructions,
            'next_hall_ticket_no' => $data['next_hall_ticket_no'] ?? $exam->next_hall_ticket_no,
        ]);

        return back()->with('success', 'Hall ticket design saved.');
    }

    public function printAllHallTickets(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'teacher', 'school'])
            ->orderBy('hall_ticket_no')
            ->get();

        return view('mcq.hall-tickets-bulk', compact('exam', 'registrations'));
    }

    public function generateHallTickets(string $tenantId, McqExam $exam, McqHallTicketService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = $service->issueBulk($exam);

        return back()->with('success', "{$count} hall ticket(s) generated.");
    }

    public function saveHalls(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'halls' => 'nullable|array|max:50',
            'halls.*.name' => 'required_with:halls|string|max:80',
            'halls.*.capacity' => 'required_with:halls|integer|min:1|max:5000',
        ]);

        app(\App\Services\Mcq\McqSeatingService::class)->saveHalls($exam, $data['halls'] ?? []);

        return back()->with('success', 'Hall plan saved.');
    }

    public function allocateSeats(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'reallocate' => 'nullable|boolean',
        ]);

        $result = app(\App\Services\Mcq\McqSeatingService::class)
            ->allocateForExam($exam, (bool) ($data['reallocate'] ?? false));

        return back()->with(
            'success',
            "Allocated seats for {$result['allocated']} candidate(s) across {$result['halls_used']} hall(s).",
        );
    }

    public function staff(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $assignments = McqExamStaff::where('exam_id', $exam->id)->with('user')->get();
        $staffPool = User::role(['exam_controller', 'exam_staff', 'mark_entry_admin'])
            ->where('tenant_id', $this->sahodaya->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->inertia('Sahodaya/Mcq/Staff', compact('exam', 'assignments', 'staffPool'));
    }

    public function storeStaff(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:controller,staff',
        ]);

        McqExamStaff::firstOrCreate(
            ['exam_id' => $exam->id, 'user_id' => $data['user_id']],
            ['role' => $data['role']],
        );

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.staff.assigned',
            "Exam staff assigned to {$exam->title}",
            ['user_id' => $data['user_id'], 'role' => $data['role']],
        );

        return back()->with('success', 'Staff assigned.');
    }

    public function generateCertificates(string $tenantId, McqExam $exam, \App\Services\Mcq\McqCertificateService $certificates)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = $certificates->issueBulk($exam);

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.certificates.generated',
            "Generated {$count} Talent Search certificate(s) for {$exam->title}",
            ['count' => $count],
        );

        return back()->with('success', "{$count} certificate(s) generated.");
    }

    public function printCertificate(string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $certificate = app(\App\Services\Mcq\McqCertificateService::class)->issue($registration->load(['exam', 'student', 'school', 'mark']));

        return view('mcq.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $this->sahodaya,
            'fields'       => app(\App\Services\Mcq\McqCertificateService::class)->fieldValues($registration, $this->sahodaya),
            'design'       => $certificate->design_snapshot_json ?? [],
        ]);
    }

    public function previewCertificate(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $service = app(\App\Services\Mcq\McqCertificateService::class);
        $exam->loadMissing('series');

        return view('mcq.certificate', [
            'registration' => null,
            'certificate'  => (object) ['verification_uuid' => 'SAMPLE-DEMO-0000'],
            'sahodaya'     => $this->sahodaya,
            'fields'       => $service->previewSampleFields($exam, $this->sahodaya),
            'design'       => $service->previewDesign($exam),
            'isSample'     => true,
        ]);
    }

    public function destroyStaff(string $tenantId, McqExam $exam, McqExamStaff $staff)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($staff->exam_id !== $exam->id, 403);
        $staff->delete();

        return back()->with('success', 'Staff removed.');
    }

    public function questionBanks(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->load('questionBanks.questions');
        $available = McqQuestionBank::where('sahodaya_id', $this->sahodaya->id)
            ->where('status', 'active')
            ->withCount('questions')
            ->latest()
            ->get();

        return $this->inertia('Sahodaya/Mcq/QuestionBanks', compact('exam', 'available'));
    }

    public function attachBank(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['bank_id' => 'required|exists:mcq_question_banks,id']);
        $bank = McqQuestionBank::where('sahodaya_id', $this->sahodaya->id)->findOrFail($data['bank_id']);

        $exam->questionBanks()->syncWithoutDetaching([$bank->id]);

        return back()->with('success', 'Question bank linked to exam.');
    }

    public function detachBank(string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $exam->questionBanks()->detach($bank->id);

        return back()->with('success', 'Question bank removed from exam.');
    }

    public function sessionMonitor(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(fn (McqRegistration $r) => [
                'id'                => $r->id,
                'student'           => $r->student?->name,
                'school'            => $r->school?->name,
                'hall_ticket_no'    => $r->hall_ticket_no,
                'status'            => $r->status,
                'attendance_status' => $r->attendance_status,
                'session_status'    => \App\Support\Mcq\McqSessionStatusPresenter::forRegistration($r, $exam),
                'started_at'        => $r->started_at?->toDateTimeString(),
                'submitted_at'      => $r->submitted_at?->toDateTimeString(),
                'score'             => $r->mark?->score,
            ]);

        return $this->inertia('Sahodaya/Mcq/SessionMonitor', compact('exam', 'registrations'));
    }

    public function bulkImportMarks(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($exam->results_published, 422, 'Results are published for this exam. Unpublish results before importing marks.');

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $result = app(\App\Services\Mcq\McqMarksAttendanceImporter::class)
            ->importMarks($request->file('file'), $exam);

        if (! $result['success'] && $result['imported'] === 0) {
            $first = $result['errors'][0]['message'] ?? 'Import failed.';

            return back()->withErrors(['file' => $first])->with('import_errors', $result['errors']);
        }

        $msg = "Imported marks for {$result['imported']} student(s).";
        if ($result['errors'] !== []) {
            $preview = collect($result['errors'])->take(8)->map(fn ($e) => "Row {$e['row']}: {$e['message']}")->implode(' · ');

            return back()->with([
                'success' => $msg,
                'import_errors' => $result['errors'],
                'warning' => $preview !== '' ? "Import issues: {$preview}" : null,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function computeRanking(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = app(\App\Services\Mcq\McqRankingService::class)->rankExam($exam);

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.ranking.computed',
            "Talent Search ranking computed for {$exam->title}",
            ['count' => $count],
        );

        return back()->with('success', "Ranking computed for {$count} student(s).");
    }

    public function activity(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $activityLogs = app(\App\Services\Audit\McqExamActivityService::class)->forExam($exam, 100);

        return $this->inertia('Sahodaya/Mcq/Activity', compact('exam', 'activityLogs'));
    }

    public function attendanceCorrections(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $requests = \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
            ->with(['registration.student', 'registration.school', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'status'            => $c->status,
                'status_label'      => $c->statusLabel(),
                'previous_status'   => $c->previous_status,
                'requested_status'  => $c->requested_status,
                'requested_note'    => $c->requested_note,
                'requested_by'      => $c->requestedBy?->name,
                'requested_by_role' => $c->requested_by_role,
                'reviewed_by'       => $c->reviewedBy?->name,
                'reviewed_at'       => $c->reviewed_at?->format('j M Y, g:i A'),
                'review_note'       => $c->review_note,
                'created_at'        => $c->created_at?->format('j M Y, g:i A'),
                'student_name'      => $c->registration?->student?->name,
                'school_name'       => $c->registration?->school?->name,
                'hall_ticket_no'    => $c->registration?->hall_ticket_no,
            ]);

        return $this->inertia('Sahodaya/Mcq/AttendanceCorrections', [
            'exam'     => $exam->only('id', 'title', 'results_published', 'delivery_mode'),
            'requests' => $requests,
        ]);
    }

    public function approveAttendanceCorrection(
        Request $request,
        string $tenantId,
        McqExam $exam,
        \App\Models\McqAttendanceCorrectionRequest $correctionRequest,
    ) {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($correctionRequest->exam_id !== $exam->id, 403);

        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        app(\App\Services\Mcq\McqAttendanceCorrectionService::class)->approve(
            $correctionRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', 'Correction approved and attendance updated.');
    }

    public function rejectAttendanceCorrection(
        Request $request,
        string $tenantId,
        McqExam $exam,
        \App\Models\McqAttendanceCorrectionRequest $correctionRequest,
    ) {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($correctionRequest->exam_id !== $exam->id, 403);

        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        app(\App\Services\Mcq\McqAttendanceCorrectionService::class)->reject(
            $correctionRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', 'Correction request rejected.');
    }
}
