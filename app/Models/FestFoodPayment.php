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
    protected $fillable = [
        'bill_id', 'amount', 'payment_mode', 'receipt_number',
        'received_by_user_id', 'received_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
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
            ]);

            $locked->recalculate();
            // Keep the caller's original $bill instance (already loaded/returned to the
            // controller) in sync with what was just persisted under the lock.
            $bill->setRawAttributes($locked->getAttributes());

            return $payment;
        });
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
