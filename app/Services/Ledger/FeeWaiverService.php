<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeWaiverService
{
    public function apply(FeeReceipt $receipt, float $waiverAmount, string $reason, ?int $userId = null): FeeReceipt
    {
        return DB::transaction(function () use ($receipt, $waiverAmount, $reason, $userId) {
            $receipt = FeeReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            abort_if($receipt->status === FeeReceipt::STATUS_APPROVED, 422, 'Reverse approved receipts before adjusting amounts.');
            $gross = round((float) $receipt->amount + (float) $receipt->waiver_amount, 2);
            if ($waiverAmount <= 0 || $waiverAmount > $gross) {
                throw ValidationException::withMessages(['waiver_amount' => 'Waiver must be positive and cannot exceed the original amount.']);
            }
            $before = $receipt->only(['amount', 'waiver_amount', 'waiver_reason']);

            $receipt->update([
                'waiver_amount' => $waiverAmount,
                'waiver_reason' => $reason,
                'waived_by_user_id' => $userId ?? auth()->id(),
                'amount' => round($gross - $waiverAmount, 2),
            ]);

            app(DataChangeLogger::class)->updated(
                $receipt,
                'Fee waiver applied',
                DataChangeLogger::diff($before, $receipt->only(['amount', 'waiver_amount', 'waiver_reason'])),
                null,
                'finance',
            );

            app(PlatformAuditLogger::class)->log(
                'fee.waiver.applied',
                "Fee waiver of {$waiverAmount} applied",
                $receipt,
                ['waiver_amount' => $waiverAmount, 'reason' => $reason],
                category: 'finance',
            );

            return $receipt->fresh();
        });
    }
}
