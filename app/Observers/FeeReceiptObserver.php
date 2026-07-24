<?php

namespace App\Observers;

use App\Models\FeeReceipt;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Services\Ledger\FeeReceiptLedgerDispatcher;
use Illuminate\Support\Facades\Log;

class FeeReceiptObserver
{
    public function updated(FeeReceipt $receipt): void
    {
        if (! $receipt->wasChanged('status') || $receipt->status !== FeeReceipt::STATUS_APPROVED) {
            return;
        }

        // System-generated "receipts" created when a FestFeeCredit is auto-applied
        // (FestSchoolEventFeeService::applyAvailableCredit()) must NEVER flow through the
        // normal approved-receipt ledger poster — that path debits CASH-BANK, and no real
        // cash moved for a credit offset. These are posted separately, without touching
        // CASH-BANK, via FestFeeLedgerService::postCreditConsumed(). This guard is
        // defense-in-depth: today applyAvailableCredit() creates the receipt already
        // 'approved' at insert time, so this observer's updated() hook never actually fires
        // for it (no created() hook exists) — but that's incidental, not structural. If a
        // created() hook is ever added here, this check is what keeps it safe.
        if ($receipt->isSystemCredit()) {
            return;
        }

        $tenantId = $this->resolveTenantId($receipt);
        if (! $tenantId) {
            Log::warning('FeeReceipt approved but tenant could not be resolved; ledger not posted', [
                'fee_receipt_id' => $receipt->id,
                'feeable_type'   => $receipt->feeable_type,
                'feeable_id'     => $receipt->feeable_id,
            ]);

            return;
        }

        $forceRepost = LedgerTransaction::where('reference_type', FeeReceipt::class)
            ->where('reference_id', $receipt->id)
            ->exists();

        app(FeeReceiptLedgerDispatcher::class)->postApproved($receipt, $tenantId, $forceRepost);
    }

    /** Exposed for reversal / reconciliation services. */
    public function resolveTenantIdPublic(FeeReceipt $receipt): ?string
    {
        return $this->resolveTenantId($receipt);
    }

    private function resolveTenantId(FeeReceipt $receipt): ?string
    {
        $feeable = $receipt->feeable;
        if (! $feeable) {
            return null;
        }

        if ($feeable instanceof TrainingRegistration) {
            $feeable->loadMissing('program');

            return $feeable->program?->tenant_id;
        }

        if ($feeable instanceof TrainingSchoolFee) {
            $feeable->loadMissing('program');

            return $feeable->program?->tenant_id;
        }

        if ($feeable instanceof McqRegistration) {
            $feeable->loadMissing('exam');

            return $feeable->exam?->tenant_id;
        }

        if ($feeable instanceof McqSchoolFee) {
            $feeable->loadMissing('exam');

            return $feeable->exam?->tenant_id;
        }

        if ($feeable instanceof FestSchoolEventFee) {
            $feeable->loadMissing('event');

            return $feeable->event?->tenant_id;
        }

        if ($feeable instanceof FestRegistration) {
            $feeable->loadMissing('event');

            return $feeable->event?->tenant_id;
        }

        return $feeable->tenant_id
            ?? $feeable->school?->parent_id
            ?? null;
    }
}
