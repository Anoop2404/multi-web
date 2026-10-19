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

        $fees = McqSchoolFee::where('exam_id', $exam->id)->get();
        $payablePerStudent = $exam->schoolPayablePerStudent();

        $settledSchoolIds = [];
        $partialFees = [];

        foreach ($fees as $fee) {
            $totalDue = (float) $fee->total_due;
            $amountPaid = (float) $fee->amount_paid;

            if (in_array($fee->status, ['approved', 'waived'], true) || ($totalDue > 0 && $amountPaid >= $totalDue)) {
                $settledSchoolIds[] = $fee->school_id;
            } elseif ($payablePerStudent > 0 && $amountPaid > 0) {
                $partialFees[] = $fee;
            }
        }

        $approvedCount = 0;
        $revertedCount = 0;

        // 1. Fully settled schools: all active registrations become approved
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

        // 2. Unsettled schools with 0 paid: revert any unverified registrations back to pending_payment
        $unpaidQuery = McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->where('approval_status', 'approved')
            ->whereDoesntHave('feeReceipt', fn ($q) => $q->where('status', 'approved'));

        if (! empty($settledSchoolIds)) {
            $unpaidQuery->whereNotIn('school_id', $settledSchoolIds);
        }
        $partialSchoolIds = array_map(fn ($f) => $f->school_id, $partialFees);
        if (! empty($partialSchoolIds)) {
            $unpaidQuery->whereNotIn('school_id', $partialSchoolIds);
        }

        $revertedCount += $unpaidQuery->update([
            'approval_status'     => 'pending_payment',
            'approved_at'         => null,
            'approved_by_user_id' => null,
        ]);

        // 3. Partially paid schools:
        // Cover first N active registrations according to amount_paid / payablePerStudent; the rest remain pending_payment
        foreach ($partialFees as $fee) {
            $amountPaid = (float) $fee->amount_paid;
            $coveredStudents = (int) floor($amountPaid / $payablePerStudent);

            $registrations = McqRegistration::where('exam_id', $exam->id)
                ->where('school_id', $fee->school_id)
                ->where('status', '!=', 'cancelled')
                ->orderBy('id')
                ->get(['id', 'approval_status']);

            $coveredIds = $registrations->take($coveredStudents)->pluck('id')->all();
            $dueIds = $registrations->slice($coveredStudents)->pluck('id')->all();

            if (! empty($coveredIds)) {
                $approvedCount += McqRegistration::whereIn('id', $coveredIds)
                    ->where('approval_status', '!=', 'approved')
                    ->update([
                        'approval_status' => 'approved',
                        'approved_at'     => now(),
                    ]);
            }

            if (! empty($dueIds)) {
                $revertedCount += McqRegistration::whereIn('id', $dueIds)
                    ->where('approval_status', 'approved')
                    ->whereDoesntHave('feeReceipt', fn ($q) => $q->where('status', 'approved'))
                    ->update([
                        'approval_status'     => 'pending_payment',
                        'approved_at'         => null,
                        'approved_by_user_id' => null,
                    ]);
            }
        }

        // 4. Any active registration with an approved individual receipt is guaranteed approved
        $approvedCount += McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->where('approval_status', '!=', 'approved')
            ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'approved'))
            ->update([
                'approval_status' => 'approved',
                'approved_at'     => now(),
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

        if ($fee->isFullyPaid() || in_array($fee->status, ['approved', 'waived'], true)) {
            return true;
        }

        $payablePerStudent = $registration->exam?->schoolPayablePerStudent() ?? 0.0;
        if ($payablePerStudent > 0 && (float) $fee->amount_paid > 0) {
            $coveredStudents = (int) floor((float) $fee->amount_paid / $payablePerStudent);
            if ($coveredStudents <= 0) {
                return false;
            }

            $rank = McqRegistration::where('exam_id', $registration->exam_id)
                ->where('school_id', $registration->school_id)
                ->where('status', '!=', 'cancelled')
                ->where('id', '<=', $registration->id)
                ->count();

            return $rank <= $coveredStudents;
        }

        return false;
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
