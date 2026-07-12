<?php

namespace App\Services\Training;

use App\Models\TrainingAttendance;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            if (Schema::hasColumn('training_attendance', 'previous_status')) {
                $payload['previous_status'] = $existing->status;
            }
        } elseif (! $existing) {
            $payload['correction_reason'] = null;
            $payload['corrected_by'] = null;
            $payload['approval_status'] = null;
            if (Schema::hasColumn('training_attendance', 'previous_status')) {
                $payload['previous_status'] = null;
            }
        }

        return TrainingAttendance::updateOrCreate(
            ['session_id' => $session->id, 'registration_id' => $registration->id],
            $payload,
        );
    }

    public function reviewCorrection(TrainingAttendance $attendance, string $decision, ?int $actorUserId = null): TrainingAttendance
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422, 'Invalid decision.');

        return DB::transaction(function () use ($attendance, $decision, $actorUserId) {
            $locked = TrainingAttendance::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->approval_status === 'pending', 422, 'No pending correction to review.');

            $updates = [
                'approval_status' => $decision,
                'corrected_by' => $actorUserId ?? Auth::id(),
            ];

            if ($decision === 'rejected' && Schema::hasColumn('training_attendance', 'previous_status') && $locked->previous_status) {
                $updates['status'] = $locked->previous_status;
                $updates['previous_status'] = null;
            }

            $locked->update($updates);

            return $locked->fresh();
        });
    }
}
