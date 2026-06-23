<?php

namespace App\Services\Ledger;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Support\AcademicYear;

class LedgerService
{
    public function postFeeReceipt(FeeReceipt $receipt, string $tenantId): void
    {
        if ($receipt->status !== 'approved') {
            return;
        }

        $head = AccountHead::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MEMBERSHIP'],
            ['name' => 'Membership Fees', 'type' => 'income', 'is_active' => true]
        );

        if (LedgerTransaction::where('reference_type', FeeReceipt::class)
            ->where('reference_id', $receipt->id)->exists()) {
            return;
        }

        LedgerTransaction::create([
            'tenant_id'        => $tenantId,
            'account_head_id'  => $head->id,
            'reference_type'   => FeeReceipt::class,
            'reference_id'     => $receipt->id,
            'entry_type'       => 'credit',
            'amount'           => $receipt->amount,
            'description'      => 'Membership fee receipt #'.$receipt->id,
            'transaction_date' => $receipt->payment_date ?? now()->toDateString(),
            'posted_by'        => $receipt->reviewed_by,
        ]);
    }
}
