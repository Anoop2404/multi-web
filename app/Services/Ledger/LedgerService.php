<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;

class LedgerService
{
    public function postFeeReceipt(FeeReceipt $receipt, string $tenantId, bool $forceRepost = false): void
    {
        if ($receipt->status !== 'approved') {
            return;
        }

        app(LedgerPostingService::class)->ensureHead($tenantId, 'MEMBERSHIP', null, 'membership');

        app(LedgerPostingService::class)->postIncomeReceipt(
            $receipt,
            $tenantId,
            'MEMBERSHIP',
            'Membership fee receipt #'.$receipt->id,
            $forceRepost
        );
    }
}
