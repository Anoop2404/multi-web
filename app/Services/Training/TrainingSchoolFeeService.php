<?php

namespace App\Services\Training;

use App\Models\FeeReceipt;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use Illuminate\Support\Facades\DB;

class TrainingSchoolFeeService
{
    public function syncForSchool(TrainingProgram $program, Tenant $school): TrainingSchoolFee
    {
        $count = TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $school->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'waitlisted'])
            ->count();

        $unit = $program->usesSchoolBatchFee()
            ? (float) ($program->fee_amount ?? 0)
            : 0.0;

        $totalDue = $unit > 0 ? round($count * $unit, 2) : 0.0;

        if ($totalDue > 0 && $program->registration_close) {
            $totalDue = app(\App\Services\Ledger\LateFeeCalculator::class)->apply(
                $totalDue,
                $program->registration_close->toDateString(),
                $program->late_fee_amount ? (float) $program->late_fee_amount : null,
                $program->penalty_amount ? (float) $program->penalty_amount : null,
            );
        }

        $fee = TrainingSchoolFee::updateOrCreate(
            ['program_id' => $program->id, 'school_id' => $school->id],
            [
                'teacher_count' => $count,
                'total_due'     => $totalDue,
            ]
        );

        if ($totalDue <= 0 && (float) $fee->amount_paid <= 0) {
            $fee->update(['status' => 'waived']);
        } else {
            $fee->refreshPaidState();
        }

        $this->markRegistrationsDeferred($program, $school);

        $fee = $fee->fresh();
        app(TrainingInvoiceService::class)->ensureForSchoolFee($fee);

        return $fee;
    }

    /**
     * Per-teacher fee is covered by the school batch — skip individual uploads.
     */
    public function markRegistrationsDeferred(TrainingProgram $program, Tenant $school): void
    {
        if (! $program->usesSchoolBatchFee()) {
            return;
        }

        TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $school->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'waitlisted'])
            ->where(function ($q) {
                $q->whereNull('fee_status')
                    ->orWhereNotIn('fee_status', ['approved', 'auto_approved']);
            })
            ->update(['fee_status' => 'auto_approved']);
    }

    public function attachPaymentProof(
        TrainingSchoolFee $schoolFee,
        string $filePath,
        ?string $transactionRef,
        float $amount,
        int $uploadedByUserId,
    ): FeeReceipt {
        FeeReceipt::supersedePriorForFeeable($schoolFee);

        $receipt = FeeReceipt::create([
            'feeable_type'        => TrainingSchoolFee::class,
            'feeable_id'          => $schoolFee->id,
            'file_path'           => $filePath,
            'transaction_ref'     => $transactionRef,
            'payment_date'        => now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);

        $schoolFee->update([
            'fee_receipt_id' => $receipt->id,
            'status'         => 'proof_uploaded',
        ]);

        return $receipt;
    }

    public function approve(TrainingSchoolFee $schoolFee, int $userId): int
    {
        $receipt = $this->pendingReceipt($schoolFee);
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        return DB::transaction(function () use ($schoolFee, $receipt, $userId) {
            $receipt->update([
                'status'      => 'approved',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $schoolFee->refresh();
            $schoolFee->refreshPaidState();

            $issued = app(ProgramFeeReceiptService::class)->issueTrainingSchoolBatch(
                $schoolFee->fresh(['program', 'school']),
                $receipt->fresh(),
            );

            $freshFee = $schoolFee->fresh();
            if ($freshFee->isFullyPaid()) {
                app(TrainingInvoiceService::class)->markPaidForSchoolFee($freshFee);
            }

            $count = 0;
            if ($freshFee->isFullyPaid()) {
                $count = $this->confirmSchoolBatch($freshFee);
            }

            app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
                $schoolFee->school,
                $issued,
                'Training batch fee',
                $schoolFee->program?->title ?? 'Training Program',
                adminPath: 'payments',
            );

            return $count;
        });
    }

    public function reject(TrainingSchoolFee $schoolFee, int $userId, string $reason): void
    {
        $receipt = $this->pendingReceipt($schoolFee);
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to reject.');

        $receipt->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => $userId,
            'reviewed_at'      => now(),
        ]);

        $schoolFee->refresh();
        $schoolFee->refreshPaidState();
    }

    public function confirmSchoolBatch(TrainingSchoolFee $schoolFee): int
    {
        $count = 0;

        TrainingRegistration::where('program_id', $schoolFee->program_id)
            ->where('school_id', $schoolFee->school_id)
            ->where('status', 'registered')
            ->orderBy('id')
            ->each(function (TrainingRegistration $registration) use (&$count) {
                $registration->update([
                    'status'     => 'confirmed',
                    'fee_status' => 'approved',
                ]);
                $count++;
            });

        TrainingRegistration::where('program_id', $schoolFee->program_id)
            ->where('school_id', $schoolFee->school_id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) {
                $q->whereNull('fee_status')->orWhere('fee_status', '!=', 'approved');
            })
            ->update(['fee_status' => 'approved']);

        return $count;
    }

    private function pendingReceipt(TrainingSchoolFee $schoolFee): ?FeeReceipt
    {
        return $schoolFee->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $schoolFee->feeReceipt;
    }
}
