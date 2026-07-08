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
            'marks_entered'=> $registrations->filter(fn ($r) => $r->mark !== null)->count(),
            'not_marked'   => $registrations->where('attendance_status', 'present')->filter(fn ($r) => $r->mark === null)->count(),
        ];

        return $this->inertia('Sahodaya/Mcq/Attendance', compact('exam', 'registrations', 'summary'));
    }

    public function storeAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_id'   => 'required|exists:mcq_registrations,id',
            'attendance_status' => 'required|in:present,absent',
        ]);

        $registration = McqRegistration::where('exam_id', $exam->id)->findOrFail($data['registration_id']);
        $registration->update([
            'attendance_status'    => $data['attendance_status'],
            'attendance_marked_at' => now(),
            'attendance_marked_by' => $request->user()->id,
        ]);

        if ($data['attendance_status'] === 'absent' && $registration->mark) {
            $registration->mark()->delete();
            $registration->update(['status' => 'registered', 'submitted_at' => null]);
        }

        return back()->with('success', 'Attendance saved.');
    }

    public function importAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) {
                continue;
            }
            [$ticket, $status] = $row;
            $status = strtolower(trim((string) $status));
            if (! in_array($status, ['present', 'absent'], true)) {
                continue;
            }

            $registration = McqRegistration::where('exam_id', $exam->id)
                ->where('hall_ticket_no', trim((string) $ticket))
                ->first();
            if (! $registration) {
                continue;
            }

            $registration->update([
                'attendance_status'    => $status,
                'attendance_marked_at' => now(),
                'attendance_marked_by' => $request->user()->id,
            ]);

            if ($status === 'absent' && $registration->mark) {
                $registration->mark()->delete();
                $registration->update(['status' => 'registered', 'submitted_at' => null]);
            }

            $imported++;
        }
        fclose($handle);

        return back()->with('success', "Imported attendance for {$imported} registration(s).");
    }

    public function exportAttendance(string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($exam);
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
            ->with(['student', 'school'])
            ->orderBy('hall_ticket_no')
            ->get();

        $design = McqHallTicketDesign::fromExam($exam);
        $logoUrl = McqHallTicketDesign::logoUrl($this->sahodaya, $design);
        $ticketsIssued = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();

        return $this->inertia('Sahodaya/Mcq/HallTickets', [
            'exam'            => $exam,
            'registrations'   => $registrations,
            'hallTicketDesign'=> $design,
            'logoUrl'         => $logoUrl,
            'previewSample'   => McqHallTicketDesign::previewSample($exam),
            'ticketsIssued'   => $ticketsIssued,
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
            ->with(['student', 'school'])
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

        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue;
            }
            [$ticket, $correct, $wrong, $unanswered] = $row;
            $registration = McqRegistration::where('exam_id', $exam->id)
                ->where('hall_ticket_no', trim($ticket))
                ->first();
            if (! $registration) {
                continue;
            }

            if ($registration->attendance_status === 'absent') {
                continue;
            }

            $correct = (int) $correct;
            $wrong = (int) $wrong;
            $unanswered = (int) $unanswered;
            $total = $correct + $wrong + $unanswered;
            $score = $correct;
            $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
            $grade = app(\App\Services\Mcq\McqGradeService::class)->gradeForPercentage($exam, $percentage);

            \App\Models\McqMark::updateOrCreate(
                ['registration_id' => $registration->id],
                [
                    'correct_count'    => $correct,
                    'wrong_count'      => $wrong,
                    'unanswered_count' => $unanswered,
                    'score'            => $score,
                    'percentage'       => $percentage,
                    'grade'            => $grade,
                    'locked_at'        => now(),
                ]
            );
            $registration->update(['status' => 'submitted', 'submitted_at' => now()]);
            $imported++;
        }
        fclose($handle);

        app(\App\Services\Mcq\McqRankingService::class)->rankExam($exam);

        return back()->with('success', "Imported marks for {$imported} student(s).");
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
}
