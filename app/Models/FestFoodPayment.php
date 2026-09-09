<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A payment recorded against a FestFoodBill. Multiple rows are expected — a school may pay
 * in advance (prepaid) and/or have cash collected at the counter over several days; each
 * just adds another row rather than the bill being a single pay/unpaid toggle.
 */
class FestFoodPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'bill_id', 'amount', 'payment_mode', 'receipt_number',
        'received_by_user_id', 'received_at', 'notes', 'status',
        'transaction_ref', 'bank_name', 'proof_path',
        'submitted_by_user_id', 'submitted_at',
        'reviewed_by_user_id', 'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(FestFoodBill::class, 'bill_id');
    }

    /**
     * @deprecated The lock this takes is released as soon as the closure returns the
     * string — the caller's actual payments()->create() then happens OUTSIDE that lock,
     * so two concurrent calls can both count the same existing rows and mint duplicate
     * receipt numbers (Phase 4 audit item 5). Kept only in case something outside
     * recordForBill() below still needs a bare number; new code should use
     * recordForBill(), which holds the lock for the whole read-count-insert sequence.
     */
    public static function generateReceiptNumber(FestFoodBill $bill): string
    {
        return DB::transaction(function () use ($bill) {
            FestFoodBill::whereKey($bill->id)->lockForUpdate()->first();

            $n = static::where('bill_id', $bill->id)->count() + 1;

            return 'FB'.$bill->id.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Atomically record a payment against $bill: the bill row is locked for the entire
     * status-check + overpayment-check + receipt-number-generation + insert + recalculate
     * sequence, so two concurrent submissions can't both pass the same checks against a
     * stale balance, and can't mint the same receipt number (see generateReceiptNumber()'s
     * docblock above — this replaces that two-step, lock-released-too-early pattern).
     * Enforces Phase 4 audit items 1, 2 and 5 in one place so both the Sahodaya-side and
     * host-school-side billing controllers share identical guarantees instead of
     * duplicating (and potentially drifting on) the same checks.
     */
    public static function recordForBill(
        FestFoodBill $bill,
        float $amount,
        string $paymentMode,
        ?string $notes,
        int $receivedByUserId,
    ): self {
        return DB::transaction(function () use ($bill, $amount, $paymentMode, $notes, $receivedByUserId) {
            $locked = FestFoodBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $locked->status !== FestFoodBill::STATUS_OPEN,
                422,
                'This bill is settled/cancelled — no further payments can be recorded.'
            );

            $balance = $locked->balanceDue();
            abort_if(
                round($amount, 2) > $balance,
                422,
                'Payment amount (₹'.number_format($amount, 2).') exceeds the outstanding balance (₹'.number_format($balance, 2).').'
            );

            $n = static::where('bill_id', $locked->id)->count() + 1;
            $receiptNumber = 'FB'.$locked->id.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);

            $payment = static::create([
                'bill_id' => $locked->id,
                'amount' => $amount,
                'payment_mode' => $paymentMode,
                'notes' => $notes,
                'receipt_number' => $receiptNumber,
                'received_by_user_id' => $receivedByUserId,
                'received_at' => now(),
                // Staff directly entering this IS the review — a front-desk/bank-transfer
                // entry a staff member typed in themselves needs no separate approval step,
                // unlike a school's own submitPayment()-based claim below.
                'status' => self::STATUS_APPROVED,
                'reviewed_by_user_id' => $receivedByUserId,
                'reviewed_at' => now(),
            ]);

            $locked->recalculate();
            // Keep the caller's original $bill instance (already loaded/returned to the
            // controller) in sync with what was just persisted under the lock.
            $bill->setRawAttributes($locked->getAttributes());

            return $payment;
        });
    }

    /**
     * A school submitting its own payment claim (proof upload + transaction ref) — unlike
     * recordForBill(), this starts as STATUS_PENDING and does NOT touch the bill's
     * amount_paid at all (recalculate() only ever sums approved rows), so a claim sitting
     * unreviewed can never make a bill look more paid-off than it actually is. No
     * overpayment-vs-balance check here on purpose: the balance a school sees already
     * excludes every pending claim, so blocking on it would only get in the way of a
     * school resubmitting proof for a payment still awaiting review.
     */
    public static function submitForBill(
        FestFoodBill $bill,
        float $amount,
        string $paymentMode,
        ?string $transactionRef,
        ?string $bankName,
        ?string $proofPath,
        ?string $notes,
        int $submittedByUserId,
    ): self {
        return DB::transaction(function () use ($bill, $amount, $paymentMode, $transactionRef, $bankName, $proofPath, $notes, $submittedByUserId) {
            $locked = FestFoodBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $locked->status !== FestFoodBill::STATUS_OPEN,
                422,
                'This bill is settled/cancelled — no further payments can be submitted.'
            );

            return static::create([
                'bill_id' => $locked->id,
                'amount' => $amount,
                'payment_mode' => $paymentMode,
                'transaction_ref' => $transactionRef,
                'bank_name' => $bankName,
                'proof_path' => $proofPath,
                'notes' => $notes,
                'status' => self::STATUS_PENDING,
                'submitted_by_user_id' => $submittedByUserId,
                'submitted_at' => now(),
            ]);
        });
    }

    /**
     * Approve a pending payment claim — only now does it count toward the bill's
     * amount_paid, via the recalculate() call below.
     */
    public function approve(int $reviewerId): void
    {
        DB::transaction(function () use ($reviewerId) {
            $locked = FestFoodBill::whereKey($this->bill_id)->lockForUpdate()->firstOrFail();

            abort_if($this->status !== self::STATUS_PENDING, 422, 'Only a pending payment can be approved.');

            $n = static::where('bill_id', $locked->id)->where('status', self::STATUS_APPROVED)->count() + 1;

            $this->update([
                'status' => self::STATUS_APPROVED,
                'receipt_number' => $this->receipt_number ?: 'FB'.$locked->id.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'received_by_user_id' => $reviewerId,
                'received_at' => now(),
                'reviewed_by_user_id' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            $locked->recalculate();
        });
    }

    /** Reject a pending payment claim — never counted toward amount_paid, so no recalculate needed. */
    public function reject(int $reviewerId, ?string $reason): void
    {
        abort_if($this->status !== self::STATUS_PENDING, 422, 'Only a pending payment can be rejected.');

        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Void this payment: deletes the row and recalculates the bill. Used when a school
     * cancels a paid food order or a payment was recorded in error — see Phase 4 audit
     * item 6. Deliberately does NOT reopen a settled bill on its own; the caller decides
     * whether the resulting (now-lower) amount_paid still clears the bill.
     *
     * NOTE: unlike FeeReceiptReversalService for competition-registration fees, this does
     * NOT post anything to the accounting ledger — food payments have no ledger
     * integration at all yet (FestFeeLedgerService only knows about FeeReceipt against
     * FestSchoolEventFee/FestRegistration). Building that out is a separate, larger piece
     * of work flagged but not done here.
     */
    public function voidPayment(): void
    {
        DB::transaction(function () {
            $bill = FestFoodBill::whereKey($this->bill_id)->lockForUpdate()->firstOrFail();
            $this->delete();
            $bill->recalculate();
        });
    }
}
