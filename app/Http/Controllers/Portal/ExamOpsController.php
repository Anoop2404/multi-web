<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\McqRegistration;
use App\Models\McqMark;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqAttendanceCorrectionService;
use Illuminate\Http\Request;

class ExamOpsController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $user = $request->user();
        $sahodaya = Tenant::findOrFail($tenantId);

        $examIds = McqExamStaff::where('user_id', $user->id)->pluck('exam_id');

        if ($user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin', 'exam_controller'])) {
            $exams = McqExam::where('tenant_id', $tenantId)
                ->whereIn('status', ['published', 'ongoing', 'completed'])
                ->orderByDesc('scheduled_at')
                ->get();
        } else {
            $exams = McqExam::whereIn('id', $examIds)
                ->whereIn('status', ['published', 'ongoing', 'completed'])
                ->orderByDesc('scheduled_at')
                ->get();
        }

        return inertia('Portal/Exam/Dashboard', [
            'sahodaya' => $sahodaya->only('id', 'name'),
            'exams'    => $exams,
            'canMark'  => $user->hasAnyRole(['exam_controller', 'mark_entry_admin', 'sahodaya_admin']),
        ]);
    }

    public function attendance(Request $request, string $tenantId, McqExam $exam)
    {
        $this->authorizeExam($request, $tenantId, $exam);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'school'])
            ->orderBy('hall_ticket_no')
            ->get();

        $pendingCorrectionsByReg = \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
            ->where('status', 'pending')
            ->get()
            ->keyBy('registration_id');

        $registrations = $registrations->map(function (McqRegistration $r) use ($pendingCorrectionsByReg) {
            $r->setAttribute('pending_correction_status', $pendingCorrectionsByReg->get($r->id)?->requested_status);

            return $r;
        });

        return inertia('Portal/Exam/Attendance', [
            'sahodaya'          => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'exam'              => $exam,
            'registrations'     => $registrations,
            'isTrustedReviewer' => $this->isTrustedRole($request),
        ]);
    }

    public function storeAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        $this->authorizeExam($request, $tenantId, $exam);

        $data = $request->validate([
            'registration_id'   => 'required|exists:mcq_registrations,id',
            'attendance_status' => 'required|in:present,absent,malpractice,withheld',
            'attendance_note'   => 'nullable|required_if:attendance_status,malpractice,withheld|string|max:1000',
        ]);

        $registration = McqRegistration::where('exam_id', $exam->id)->findOrFail($data['registration_id']);

        // Assigned exam-day staff (not Sahodaya admin/mark-entry-admin/exam-controller) can only
        // freely mark attendance the first time; any change after that goes through approval.
        if (! $this->isTrustedRole($request) && app(McqAttendanceCorrectionService::class)->requiresApproval($registration)) {
            app(McqAttendanceCorrectionService::class)->submit(
                $registration,
                $data['attendance_status'],
                $data['attendance_note'] ?? null,
                $request->user(),
                'exam_portal_staff',
            );

            return back()->with('success', 'This attendance change needs Sahodaya approval and has been submitted for review.');
        }

        $registration->update([
            'attendance_status'      => $data['attendance_status'],
            'attendance_note'        => $data['attendance_note'] ?? null,
            'attendance_marked_at'   => now(),
            'attendance_marked_by'   => $request->user()->id,
        ]);

        if ($registration->blocksScoring() && $registration->mark) {
            $registration->mark()->delete();
            $registration->update(['status' => 'registered', 'submitted_at' => null]);
        }

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.attendance.marked',
            "Attendance marked {$data['attendance_status']} for registration #{$registration->id}",
        );

        return back()->with('success', 'Attendance saved.');
    }

    public function marks(Request $request, string $tenantId, McqExam $exam)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['exam_controller', 'mark_entry_admin', 'sahodaya_admin']), 403);

        $this->authorizeExam($request, $tenantId, $exam);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('attendance_status', 'present')
            ->with(['student', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->get();

        return inertia('Portal/Exam/MarkEntry', [
            'sahodaya'      => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'exam'          => $exam,
            'registrations' => $registrations,
            'gradeBands'    => app(\App\Services\Mcq\McqGradeService::class)->bandsForExam($exam),
        ]);
    }

    public function storeMark(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['exam_controller', 'mark_entry_admin', 'sahodaya_admin']), 403);
        abort_if($registration->exam_id !== $exam->id, 403);
        $this->authorizeExam($request, $tenantId, $exam);

        $data = $request->validate([
            'correct_count'    => 'required|integer|min:0',
            'wrong_count'      => 'required|integer|min:0',
            'unanswered_count' => 'required|integer|min:0',
            'score'            => 'required|numeric|min:0',
            'grade'            => 'nullable|string|max:20',
        ]);

        app(\App\Services\Mcq\McqMarkSaveService::class)->save($exam, $registration, $data, $user->id);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam']),
            'mcq.mark.entered',
            "Exam controller entered marks for registration #{$registration->id}",
        );

        return back()->with('success', 'Marks saved.');
    }

    public function supervision(Request $request, string $tenantId, McqExam $exam)
    {
        $this->authorizeExam($request, $tenantId, $exam);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(fn (McqRegistration $r) => [
                'id'                 => $r->id,
                'student_name'       => $r->student?->name,
                'school_name'        => $r->school?->name,
                'hall_ticket_no'     => $r->hall_ticket_no,
                'status'             => $r->status,
                'attendance_status'  => $r->attendance_status,
                'started_at'         => $r->started_at?->toIso8601String(),
                'submitted_at'       => $r->submitted_at?->toIso8601String(),
                'score'              => $r->mark?->score,
            ]);

        $summary = [
            'total'       => $registrations->count(),
            'present'     => $registrations->where('attendance_status', 'present')->count(),
            'started'     => $registrations->whereIn('status', ['started', 'submitted'])->count(),
            'submitted'   => $registrations->where('status', 'submitted')->count(),
            'absent'      => $registrations->where('attendance_status', 'absent')->count(),
            'malpractice' => $registrations->where('attendance_status', 'malpractice')->count(),
            'withheld'    => $registrations->where('attendance_status', 'withheld')->count(),
        ];

        return inertia('Portal/Exam/Supervision', [
            'sahodaya'      => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'exam'          => $exam->only('id', 'title', 'status', 'scheduled_at', 'duration_minutes'),
            'registrations' => $registrations,
            'summary'       => $summary,
        ]);
    }

    public function importAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        $this->authorizeExam($request, $tenantId, $exam);

        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);

        $trusted = $this->isTrustedRole($request);
        $correctionService = app(McqAttendanceCorrectionService::class);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;
        $queued = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if (! $data) {
                continue;
            }

            $registration = McqRegistration::where('exam_id', $exam->id)
                ->when(! empty($data['registration_id']), fn ($q) => $q->where('id', $data['registration_id']))
                ->when(! empty($data['hall_ticket_no']), fn ($q) => $q->where('hall_ticket_no', $data['hall_ticket_no']))
                ->when(! empty($data['student_id']), fn ($q) => $q->where('student_id', $data['student_id']))
                ->first();

            if (! $registration || empty($data['attendance_status'])) {
                continue;
            }

            if (! in_array($data['attendance_status'], ['present', 'absent', 'malpractice', 'withheld'], true)) {
                continue;
            }

            $registration->setRelation('exam', $exam);

            if (! $trusted && $correctionService->requiresApproval($registration)) {
                $correctionService->submit(
                    $registration,
                    $data['attendance_status'],
                    $data['attendance_note'] ?? null,
                    $request->user(),
                    'exam_portal_staff',
                );
                $queued++;

                continue;
            }

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

            app(PlatformAuditLogger::class)->mcqRegistration(
                $registration->fresh(['exam']),
                'mcq.attendance.imported',
                "Bulk attendance: {$data['attendance_status']} for registration #{$registration->id}",
            );

            $imported++;
        }

        fclose($handle);

        $message = "Imported attendance for {$imported} registration(s).";
        if ($queued > 0) {
            $message .= " {$queued} change(s) sent to the Sahodaya for approval.";
        }

        return back()->with('success', $message);
    }

    private function authorizeExam(Request $request, string $tenantId, McqExam $exam): void
    {
        abort_if($exam->tenant_id !== $tenantId, 403);

        $user = $request->user();
        if ($this->isTrustedRole($request)) {
            return;
        }

        $assigned = McqExamStaff::where('exam_id', $exam->id)->where('user_id', $user->id)->exists();
        abort_unless($assigned, 403);
    }

    /** Sahodaya-side trusted roles who may directly overwrite attendance/marks at any time. */
    private function isTrustedRole(Request $request): bool
    {
        return $request->user()->hasAnyRole(['sahodaya_admin', 'mark_entry_admin', 'exam_controller']);
    }
}
