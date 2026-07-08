<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McqRegistrationApprovalService
{
    public function initialApprovalStatus(McqExam $exam): string
    {
        return 'pending_payment';
    }

    public function feeVerified(McqRegistration $registration): bool
    {
        if ($registration->feeReceipt?->status === 'approved') {
            return true;
        }

        return McqSchoolFee::where('exam_id', $registration->exam_id)
            ->where('school_id', $registration->school_id)
            ->where('status', 'approved')
            ->exists();
    }

    public function canApprove(McqRegistration $registration): bool
    {
        if ($registration->status === 'cancelled') {
            return false;
        }

        if (in_array($registration->approval_status, ['approved', 'rejected'], true)) {
            return false;
        }

        return $this->feeVerified($registration);
    }

    public function approve(McqRegistration $registration, ?int $approvedByUserId = null): McqRegistration
    {
        if (! $this->canApprove($registration)) {
            throw ValidationException::withMessages([
                'approval' => 'Fee must be verified before approving this registration.',
            ]);
        }

        return DB::transaction(function () use ($registration, $approvedByUserId) {
            $registration->update([
                'approval_status'     => 'approved',
                'approved_at'         => now(),
                'approved_by_user_id' => $approvedByUserId,
            ]);

            $registration = app(McqHallTicketService::class)
                ->issueForRegistration($registration->fresh(['exam', 'student']));

            app(McqExamNotifier::class)->registrationApproved($registration);

            app(PlatformAuditLogger::class)->mcqRegistration(
                $registration,
                'mcq.registration.approved',
                "Registration approved; hall ticket #{$registration->hall_ticket_no} issued",
            );

            return $registration;
        });
    }

    public function reject(McqRegistration $registration, ?int $rejectedByUserId = null, ?string $reason = null): McqRegistration
    {
        if ($registration->approval_status === 'approved') {
            throw ValidationException::withMessages([
                'approval' => 'Approved registrations cannot be rejected.',
            ]);
        }

        $registration->update([
            'approval_status'     => 'rejected',
            'approved_at'         => now(),
            'approved_by_user_id' => $rejectedByUserId,
        ]);

        app(PlatformAuditLogger::class)->mcqRegistration(
            $registration->fresh(['exam', 'student']),
            'mcq.registration.rejected',
            $reason ? "Registration rejected: {$reason}" : 'Registration rejected',
        );

        return $registration->fresh();
    }

    public function approveSchoolBatch(McqSchoolFee $schoolFee, int $userId): int
    {
        $count = 0;

        McqRegistration::where('exam_id', $schoolFee->exam_id)
            ->where('school_id', $schoolFee->school_id)
            ->where('approval_status', 'pending_payment')
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->each(function (McqRegistration $registration) use ($userId, &$count) {
                if (! $this->canApprove($registration)) {
                    return;
                }

                $this->approve($registration, $userId);
                $count++;
            });

        return $count;
    }
}
