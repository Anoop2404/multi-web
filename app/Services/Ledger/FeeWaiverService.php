<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;

class FeeWaiverService
{
    public function apply(FeeReceipt $receipt, float $waiverAmount, string $reason, ?int $userId = null): FeeReceipt
    {
        $before = $receipt->only(['amount', 'waiver_amount', 'waiver_reason']);

        $receipt->update([
            'waiver_amount'     => $waiverAmount,
            'waiver_reason'     => $reason,
            'waived_by_user_id' => $userId ?? auth()->id(),
            'amount'            => max(0, (float) $receipt->amount - $waiverAmount),
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
    }
}
