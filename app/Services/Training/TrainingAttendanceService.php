<?php

namespace App\Services\Training;

use App\Models\TrainingAttendance;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\Auth;

class TrainingAttendanceService
{
    /**
     * Mark or correct attendance for a session participant.
     *
     * @param  array{status: string, correction_reason?: ?string, require_approval?: bool}  $input
     */
    public function updateAttendance(
        TrainingSession $session,
        TrainingRegistration $registration,
        array $input,
        ?int $actorUserId = null,
    ): TrainingAttendance {
        $status = $input['status'];
        $actorUserId ??= Auth::id();
        $requireApproval = (bool) ($input['require_approval'] ?? false);

        $existing = TrainingAttendance::where('session_id', $session->id)
            ->where('registration_id', $registration->id)
            ->first();

        $payload = [
            'status' => $status,
            'marked_by' => $actorUserId,
            'marked_at' => now(),
        ];

        if ($existing && $existing->status !== $status) {
            $payload['correction_reason'] = $input['correction_reason'] ?? $existing->correction_reason;
            $payload['corrected_by'] = $actorUserId;
            $payload['approval_status'] = $requireApproval ? 'pending' : 'approved';
        } elseif (! $existing) {
            $payload['correction_reason'] = null;
            $payload['corrected_by'] = null;
            $payload['approval_status'] = null;
        }

        return TrainingAttendance::updateOrCreate(
            ['session_id' => $session->id, 'registration_id' => $registration->id],
            $payload,
        );
    }

    public function reviewCorrection(TrainingAttendance $attendance, string $decision, ?int $actorUserId = null): TrainingAttendance
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422, 'Invalid decision.');
        abort_unless($attendance->approval_status === 'pending', 422, 'No pending correction to review.');

        $attendance->update([
            'approval_status' => $decision,
            'corrected_by' => $actorUserId ?? Auth::id(),
        ]);

        // On reject, revert is left to a follow-up mark — MVP keeps the corrected status
        // but flags rejection so Sahodaya can re-mark.

        return $attendance->fresh();
    }
}
