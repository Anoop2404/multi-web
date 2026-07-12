<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McqSchoolFeeService
{
    public function syncForSchool(McqExam $exam, Tenant $school): McqSchoolFee
    {
        $count = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        $payablePerStudent = $exam->schoolPayablePerStudent();
        $totalDue = $exam->hasFee() ? round($count * $payablePerStudent, 2) : 0;
        $totalDue = app(\App\Services\Ledger\LateFeeCalculator::class)->apply(
            $totalDue,
            $exam->payment_deadline?->toDateString(),
            $exam->late_fee_amount ? (float) $exam->late_fee_amount : null,
            $exam->penalty_amount ? (float) $exam->penalty_amount : null,
        );

        $fee = McqSchoolFee::updateOrCreate(
            ['exam_id' => $exam->id, 'school_id' => $school->id],
            [
                'student_count' => $count,
                'total_due'     => $totalDue,
            ]
        );

        // Derive status from what's actually been paid so a partial balance survives resyncs.
        if ($totalDue <= 0 && (float) $fee->amount_paid <= 0) {
            $fee->update(['status' => 'waived']);
        } else {
            $fee->refreshPaidState();
        }

        return $fee->fresh();
    }

    public function markRegistrationsPaid(McqSchoolFee $schoolFee): void
    {
        // Fee verification is handled by McqRegistrationApprovalService::approveSchoolBatch().
    }

    public function approve(McqSchoolFee $schoolFee, int $userId): int
    {
        $receipt = $this->pendingReceipt($schoolFee);
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        return DB::transaction(function () use ($schoolFee, $receipt, $userId) {
            $receipt->update([
                'status'      => 'approved',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            // Accumulate this receipt into amount_paid and derive partial/approved status.
            $schoolFee->refresh();
            $schoolFee->refreshPaidState();

            $issued = app(ProgramFeeReceiptService::class)->issueMcqSchoolBatch(
                $schoolFee->fresh(['exam', 'school']),
                $receipt->fresh(),
            );

            // Hall tickets are only issued once the batch fee is fully settled.
            $count = 0;
            if ($schoolFee->fresh()->isFullyPaid()) {
                $count = app(McqRegistrationApprovalService::class)->approveSchoolBatch($schoolFee->fresh(), $userId);
            }

            app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
                $schoolFee->school,
                $issued,
                'Talent Search exam fee',
                $schoolFee->exam?->title ?? 'Talent Search Exam',
                adminPath: 'payments',
            );

            $schoolFee->loadMissing(['exam', 'school']);
            if ($schoolFee->exam) {
                app(PlatformAuditLogger::class)->mcq(
                    $schoolFee->exam,
                    'mcq.fee.approved',
                    "Talent Search batch fee approved for {$schoolFee->school?->name}",
                    [
                        'school_id' => $schoolFee->school_id,
                        'fee_receipt_id' => $receipt->id,
                        'amount' => $receipt->amount,
                        'registrations_confirmed' => $count,
                    ],
                    $schoolFee,
                );
            }

            return $count;
        });
    }

    public function reject(McqSchoolFee $schoolFee, int $userId, string $reason): void
    {
        $receipt = $this->pendingReceipt($schoolFee);
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to reject.');

        $receipt->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => $userId,
            'reviewed_at'      => now(),
        ]);

        // Fall back to whatever has already been paid (partial) or pending.
        $schoolFee->refresh();
        $schoolFee->refreshPaidState();

        $schoolFee->loadMissing(['exam', 'school']);
        if ($schoolFee->exam) {
            app(PlatformAuditLogger::class)->mcq(
                $schoolFee->exam,
                'mcq.fee.rejected',
                "Talent Search batch fee rejected for {$schoolFee->school?->name}",
                ['school_id' => $schoolFee->school_id, 'reason' => $reason],
                $schoolFee,
            );
        }
    }

    private function pendingReceipt(McqSchoolFee $schoolFee): ?\App\Models\FeeReceipt
    {
        return $schoolFee->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $schoolFee->feeReceipt;
    }

    /**
     * @return array{
     *     student_fee: float,
     *     school_discount: float,
     *     payable_per_student: float,
     *     student_count: int,
     *     student_fee_total: float,
     *     discount_total: float,
     *     payable_total: float,
     *     by_class: list<array{class_id: int|null, class_name: string, student_count: int, student_fee_total: float, discount_total: float, payable_total: float}>
     * }
     */
    public function breakdownForSchool(McqExam $exam, Tenant $school): array
    {
        $studentFee = $exam->hasFee() ? (float) $exam->fee_amount : 0.0;
        $discount = $exam->schoolDiscountAmount();
        $payable = $exam->schoolPayablePerStudent();

        $registrations = McqRegistration::query()
            ->where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->where('status', '!=', 'cancelled')
            ->with('student.schoolClass:id,name')
            ->get();

        $byClass = $registrations
            ->groupBy(fn (McqRegistration $reg) => $reg->student?->school_class_id ?? 0)
            ->map(function ($rows, $classId) use ($studentFee, $discount, $payable) {
                $count = $rows->count();
                $className = $rows->first()?->student?->schoolClass?->name ?? 'Unassigned';

                return [
                    'class_id'          => $classId ? (int) $classId : null,
                    'class_name'        => $className,
                    'student_count'     => $count,
                    'student_fee_total' => round($count * $studentFee, 2),
                    'discount_total'    => round($count * $discount, 2),
                    'payable_total'     => round($count * $payable, 2),
                ];
            })
            ->sortBy('class_name')
            ->values()
            ->all();

        $count = $registrations->count();

        return [
            'student_fee'         => $studentFee,
            'school_discount'     => $discount,
            'payable_per_student' => $payable,
            'student_count'       => $count,
            'student_fee_total'   => round($count * $studentFee, 2),
            'discount_total'      => round($count * $discount, 2),
            'payable_total'       => round($count * $payable, 2),
            'by_class'            => $byClass,
        ];
    }
}
