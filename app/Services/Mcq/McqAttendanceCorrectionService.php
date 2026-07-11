<?php

namespace App\Services\Mcq;

use App\Models\McqAttendanceCorrectionRequest;
use App\Models\McqRegistration;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McqAttendanceCorrectionService
{
    /**
     * Whether a direct attendance overwrite is still allowed for this registration,
     * or whether the change must go through the correction-approval workflow.
     *
     * Direct edits are only allowed for a registration's first-ever attendance mark
     * (still null/pending) and only while the exam's results are not yet published.
     * Anything after that — from non-Sahodaya-admin callers — must be requested.
     */
    public function requiresApproval(McqRegistration $registration): bool
    {
        $exam = $registration->exam ?? $registration->loadMissing('exam')->exam;

        if ($exam?->results_published) {
            return true;
        }

        return ! in_array($registration->attendance_status, [null, 'pending'], true);
    }

    /** @return array{applied: bool, request: ?McqAttendanceCorrectionRequest} */
    public function submit(
        McqRegistration $registration,
        string $requestedStatus,
        ?string $requestedNote,
        User $requestedBy,
        ?string $requestedByRole = null,
    ): array {
        if (! $this->requiresApproval($registration)) {
            return ['applied' => false, 'request' => null];
        }

        $registration->loadMissing('exam');

        // Avoid piling up duplicate pending requests for the same registration —
        // update the existing one in place instead.
        $correctionRequest = McqAttendanceCorrectionRequest::where('registration_id', $registration->id)
            ->where('status', 'pending')
            ->first();

        $attributes = [
            'tenant_id'         => $registration->exam->tenant_id,
            'exam_id'           => $registration->exam_id,
            'registration_id'   => $registration->id,
            'previous_status'   => $registration->attendance_status,
            'previous_note'     => $registration->attendance_note,
            'requested_status'  => $requestedStatus,
            'requested_note'    => $requestedNote,
            'requested_by'      => $requestedBy->id,
            'requested_by_role' => $requestedByRole,
            'status'            => 'pending',
        ];

        if ($correctionRequest) {
            $correctionRequest->update($attributes);
        } else {
            $correctionRequest = McqAttendanceCorrectionRequest::create($attributes);
        }

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration,
            'mcq.attendance_correction.requested',
            "Attendance correction requested for registration #{$registration->id}: ".
                ($registration->attendance_status ?? 'pending')." -> {$requestedStatus}",
        );

        $this->notifySahodayaAdmins($correctionRequest, $requestedBy);

        return ['applied' => false, 'request' => $correctionRequest];
    }

    public function approve(McqAttendanceCorrectionRequest $correctionRequest, User $reviewer, ?string $reviewNote = null): McqRegistration
    {
        if (! $correctionRequest->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }

        return DB::transaction(function () use ($correctionRequest, $reviewer, $reviewNote) {
            $registration = McqRegistration::findOrFail($correctionRequest->registration_id);

            $registration->update([
                'attendance_status'    => $correctionRequest->requested_status,
                'attendance_note'      => $correctionRequest->requested_note,
                'attendance_marked_at' => now(),
                'attendance_marked_by' => $reviewer->id,
            ]);

            if ($registration->blocksScoring() && $registration->mark) {
                $registration->mark()->delete();
                $registration->update(['status' => 'registered', 'submitted_at' => null]);
            }

            $correctionRequest->update([
                'status'      => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            app(PlatformAuditLogger::class)->mcqRegistration(
                $registration,
                'mcq.attendance_correction.approved',
                "Attendance correction #{$correctionRequest->id} approved: attendance set to {$correctionRequest->requested_status}",
            );

            $this->notifyRequester($correctionRequest, 'mcq.attendance_correction.approved');

            return $registration;
        });
    }

    public function reject(McqAttendanceCorrectionRequest $correctionRequest, User $reviewer, ?string $reviewNote = null): McqAttendanceCorrectionRequest
    {
        if (! $correctionRequest->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }

        $correctionRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reviewNote,
        ]);

        $registration = McqRegistration::find($correctionRequest->registration_id);
        if ($registration) {
            app(PlatformAuditLogger::class)->mcqRegistration(
                $registration,
                'mcq.attendance_correction.rejected',
                "Attendance correction #{$correctionRequest->id} rejected".($reviewNote ? ": {$reviewNote}" : '.'),
            );
        }

        $this->notifyRequester($correctionRequest, 'mcq.attendance_correction.rejected');

        return $correctionRequest;
    }

    private function notifySahodayaAdmins(McqAttendanceCorrectionRequest $correctionRequest, User $requestedBy): void
    {
        $correctionRequest->loadMissing(['exam', 'registration.student']);
        $service = app(NotificationService::class);

        $replacements = [
            'requested_by'      => $requestedBy->name,
            'student_name'      => $correctionRequest->registration?->student?->name ?? 'Student',
            'exam_title'        => $correctionRequest->exam?->title ?? '',
            'requested_status'  => ucfirst($correctionRequest->requested_status),
        ];

        foreach (User::role('sahodaya_admin')->where('tenant_id', $correctionRequest->tenant_id)->get() as $admin) {
            $service->notifyFromTemplate(
                $admin,
                'mcq.attendance_correction.requested',
                $replacements,
                "/sahodaya-admin/{$correctionRequest->tenant_id}/mcq-exams/{$correctionRequest->exam_id}/attendance-corrections",
            );
        }
    }

    private function notifyRequester(McqAttendanceCorrectionRequest $correctionRequest, string $slug): void
    {
        $correctionRequest->loadMissing(['exam', 'requestedBy', 'registration.student']);
        $requestedBy = $correctionRequest->requestedBy;
        if (! $requestedBy) {
            return;
        }

        app(NotificationService::class)->notifyFromTemplate($requestedBy, $slug, [
            'student_name'     => $correctionRequest->registration?->student?->name ?? 'Student',
            'exam_title'       => $correctionRequest->exam?->title ?? '',
            'requested_status' => ucfirst($correctionRequest->requested_status),
            'reason'           => $correctionRequest->review_note ?? '',
        ]);
    }
}
