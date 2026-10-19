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
        return $exam->hasFee() ? 'pending_payment' : 'approved';
    }

    /**
     * Synchronize approval_status on mcq_registrations to match actual batch fee settlement.
     *
     * If an exam has no fees, all active candidates are approved.
     * If an exam has fees:
     * - Candidates whose school batch fee is fully paid or waived (or with an approved individual receipt) are approved.
     * - Candidates whose school batch fee is pending/unsettled and lack an approved individual receipt are marked pending_payment.
     *
     * @return array{approved: int, pending_payment: int}
     */
    public function syncExamApprovalStatuses(McqExam $exam): array
    {
        if (! $exam->hasFee()) {
            $updated = McqRegistration::where('exam_id', $exam->id)
                ->where('status', '!=', 'cancelled')
                ->where('approval_status', '!=', 'approved')
                ->update([
                    'approval_status' => 'approved',
                    'approved_at'     => now(),
                ]);

            return ['approved' => $updated, 'pending_payment' => 0];
        }

        // Settled schools: batch fee status is 'approved' or 'waived', or amount_paid >= total_due
        $settledSchoolIds = McqSchoolFee::where('exam_id', $exam->id)
            ->where(function ($query) {
                $query->whereIn('status', ['approved', 'waived'])
                    ->orWhereRaw('total_due > 0 AND amount_paid >= total_due');
            })
            ->pluck('school_id')
            ->all();

        $approvedCount = 0;
        if (! empty($settledSchoolIds)) {
            $approvedCount += McqRegistration::where('exam_id', $exam->id)
                ->where('status', '!=', 'cancelled')
                ->whereIn('school_id', $settledSchoolIds)
                ->where('approval_status', '!=', 'approved')
                ->update([
                    'approval_status' => 'approved',
                    'approved_at'     => now(),
                ]);
        }

        $approvedCount += McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->where('approval_status', '!=', 'approved')
            ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'approved'))
            ->update([
                'approval_status' => 'approved',
                'approved_at'     => now(),
            ]);

        // Candidates who were falsely marked 'approved' (e.g. by renumberByClass) but whose
        // school has NOT settled the batch fee and who have no individual approved receipt,
        // are reverted back to 'pending_payment'.
        $revertQuery = McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->where('approval_status', 'approved')
            ->whereDoesntHave('feeReceipt', fn ($q) => $q->where('status', 'approved'));

        if (! empty($settledSchoolIds)) {
            $revertQuery->whereNotIn('school_id', $settledSchoolIds);
        }

        $revertedCount = $revertQuery->update([
            'approval_status'     => 'pending_payment',
            'approved_at'         => null,
            'approved_by_user_id' => null,
        ]);

        return ['approved' => $approvedCount, 'pending_payment' => $revertedCount];
    }


    public function feeVerified(McqRegistration $registration): bool
    {
        if (! $registration->exam?->hasFee()) {
            return true;
        }

        if ($registration->feeReceipt?->status === 'approved') {
            return true;
        }

        $fee = McqSchoolFee::where('exam_id', $registration->exam_id)
            ->where('school_id', $registration->school_id)
            ->first();

        if (! $fee) {
            return false;
        }

        return $fee->isFullyPaid() || in_array($fee->status, ['approved', 'waived'], true);
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

            try {
                app(McqExamNotifier::class)->registrationApproved($registration);
            } catch (\Throwable $exception) {
                report($exception);
            }

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
            'rejection_reason'    => $reason,
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
            ->whereIn('approval_status', ['pending_payment', 'pending_approval'])
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
