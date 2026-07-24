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
    /**
     * @param  ?string  $cancellationReason  When set (i.e. this sync was triggered by a
     *     cancellation, not a routine resync), and the recount causes total_due to drop
     *     below what the school already had approved-paid, the freed amount is recorded
     *     as a ProgramFeeCredit instead of silently vanishing. Same delta-snapshot
     *     technique as McqSchoolFeeService::syncForSchool() / FestRegistrationBulkService::
     *     rejectMany(). Leave null for ordinary (non-cancellation) resyncs so nothing here
     *     changes behavior for ordinary registration/attendance/import flows.
     *     See docs/FLOW_GAP_FIX_PLAN.md Phase 1.1.
     */
    public function syncForSchool(
        TrainingProgram $program,
        Tenant $school,
        ?string $cancellationReason = null,
        ?int $cancelledByUserId = null,
        ?int $sourceRegistrationId = null,
    ): TrainingSchoolFee {
        // Snapshot before recalculating so a cancellation-triggered drop in total_due can
        // be measured against what was already paid.
        $existingFee = TrainingSchoolFee::where('program_id', $program->id)->where('school_id', $school->id)->first();
        $dueBefore   = (float) ($existingFee?->total_due ?? 0);
        $paidBefore  = (float) ($existingFee?->amount_paid ?? 0);

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

        // Batch-fee credit on cancellation — mirrors McqSchoolFeeService::syncForSchool().
        // Never fires on an ordinary resync (no $cancellationReason) and never double-issues
        // (only when the recount actually reduced total_due below what was already paid).
        if ($program->usesSchoolBatchFee()) {
            $reduction = round($dueBefore - $totalDue, 2);
            if ($reduction > 0 && $paidBefore > 0 && $cancellationReason !== null) {
                $credit = \App\Models\ProgramFeeCredit::create([
                    'creditable_type'    => TrainingSchoolFee::class,
                    'creditable_id'      => $fee->id,
                    'source_type'        => TrainingRegistration::class,
                    'source_id'          => $sourceRegistrationId ?? 0,
                    'amount'             => min($reduction, $paidBefore),
                    'reason'             => $cancellationReason,
                    'created_by_user_id' => $cancelledByUserId ?? auth()->id(),
                ]);

                try {
                    app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                } catch (\Throwable) {
                    // credit is already recorded; the note can be regenerated later
                }
            }
        }

        $this->markRegistrationsDeferred($program, $school);

        $fee = $fee->fresh();
        app(TrainingInvoiceService::class)->ensureForSchoolFee($fee);

        return $fee;
    }

    /**
     * Credit path for the *individually*-billed side (usesSchoolBatchFee() === false),
     * where TrainingRegistration itself — not a TrainingSchoolFee row — is the fee
     * carrier (via TracksPartialPayments). syncForSchool()'s batch-delta logic can't see
     * this money at all (individually-billed programs always price the school aggregate
     * at 0 — see TrainingSchoolFeeService::syncForSchool()'s $unit calc), so a cancelled,
     * already-paid individual registration needs its own credit path.
     *
     * NOTE: as of this writing, `training_registrations.total_due` is never populated
     * anywhere in the codebase (confirmed via migration search — only `amount_paid` and
     * `fee_status` were added by 2026_07_23_000001_partial_fee_payments.php), so
     * TrainingRegistration::outstandingBalance() always reads a $0 due and both
     * SahodayaAdmin\TrainingProgramController::recordPayment() and
     * SchoolAdmin\TrainingRegistrationController::uploadPayment() abort with "already
     * fully paid" before a receipt can ever be created. That means amount_paid > 0 on an
     * individually-billed registration should not currently be reachable in production.
     * This method still guards on amount_paid > 0 (not total_due) so it behaves correctly
     * the moment that separate, pre-existing bug is fixed — it is intentionally not fixed
     * here, since populating total_due touches registration-creation call sites this
     * pass never audited. Flagged in docs/FLOW_GAP_FIX_PLAN.md Phase 1.1.
     */
    public function creditForCancelledIndividualRegistration(
        TrainingRegistration $registration,
        string $reason,
        int $userId,
    ): void {
        $paid = (float) $registration->amount_paid;
        if ($paid <= 0) {
            return;
        }

        $fee = TrainingSchoolFee::firstOrCreate(
            ['program_id' => $registration->program_id, 'school_id' => $registration->school_id],
            ['teacher_count' => 0, 'total_due' => 0],
        );

        $credit = \App\Models\ProgramFeeCredit::create([
            'creditable_type'    => TrainingSchoolFee::class,
            'creditable_id'      => $fee->id,
            'source_type'        => TrainingRegistration::class,
            'source_id'          => $registration->id,
            'amount'             => $paid,
            'reason'             => $reason,
            'created_by_user_id' => $userId,
        ]);

        try {
            app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
        } catch (\Throwable) {
            // credit is already recorded; the note can be regenerated later
        }

        // Nothing is owed on a cancelled registration anymore. forceFill+saveQuietly (not
        // refreshPaidState()) deliberately, because refreshPaidState() would flip
        // fee_status back to 'approved' once due<=0 — misleading for a cancelled
        // registration. The row's own status='cancelled' is what payment-history keys off
        // for the "CANCELLED" label regardless of fee_status (see Phase 3).
        //
        // Guarded with hasColumn() because `training_registrations.total_due` does not
        // exist today (see this method's docblock) — forceFill+save on a non-existent
        // column would throw a SQL error. This makes the guard self-updating: once that
        // column is added, this starts zeroing it automatically with no further change here.
        if (\Illuminate\Support\Facades\Schema::hasColumn('training_registrations', 'total_due')
            && (float) ($registration->total_due ?? 0) !== 0.0) {
            $registration->forceFill(['total_due' => 0])->saveQuietly();
        }
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
