<?php

namespace App\Models\Concerns;

use App\Models\FeeReceipt;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared partial-payment helpers for fee carriers (MCQ school fee, fest event fee,
 * training registration). A carrier may collect several approved receipts until the
 * cumulative amount covers the amount due; until then it sits in a "partial" state.
 */
trait TracksPartialPayments
{
    public function receipts(): MorphMany
    {
        return $this->morphMany(FeeReceipt::class, 'feeable');
    }

    /** Total amount actually due for this carrier. */
    public function feeTotalDue(): float
    {
        return (float) ($this->total_due ?? 0);
    }

    /** Sum of all approved receipts (net of any waiver already deducted on the receipt). */
    public function approvedPaidTotal(): float
    {
        return (float) $this->receipts()
            ->where('status', 'approved')
            ->sum('amount');
    }

    public function outstandingBalance(): float
    {
        return round(max(0, $this->feeTotalDue() - (float) ($this->amount_paid ?? 0)), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->feeTotalDue() <= 0 || $this->outstandingBalance() <= 0;
    }

    public function isPartiallyPaid(): bool
    {
        return ! $this->isFullyPaid() && (float) ($this->amount_paid ?? 0) > 0;
    }

    /**
     * Recompute amount_paid from approved receipts and derive the payment status
     * (pending → proof_uploaded → partial → approved). Persists both fields.
     */
    public function refreshPaidState(string $statusColumn = 'status'): void
    {
        $paid = round($this->approvedPaidTotal(), 2);
        $due = $this->feeTotalDue();
        $hasUploaded = $this->receipts()->whereNotIn('status', ['approved', 'rejected', 'superseded', 'reversed'])->whereNotNull('file_path')->exists()
            || ($this->feeReceipt && !empty($this->feeReceipt->file_path) && !in_array($this->feeReceipt->status, ['approved', 'rejected', 'superseded', 'reversed'], true));

        $status = match (true) {
            $due <= 0 => ($this->{$statusColumn} === 'waived' ? 'waived' : 'approved'),
            $paid >= $due => 'approved',
            $paid > 0 => 'partial',
            $hasUploaded => 'proof_uploaded',
            default => 'pending',
        };

        $this->forceFill([
            'amount_paid' => $paid,
            $statusColumn => $status,
        ])->save();
    }
}
