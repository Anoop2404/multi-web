<?php

namespace App\Observers;

use App\Models\FeeReceipt;
use App\Models\LedgerTransaction;
use App\Models\FestSchoolEventFee;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\TrainingRegistration;
use App\Services\Ledger\FeeReceiptLedgerDispatcher;

class FeeReceiptObserver
{
    public function updated(FeeReceipt $receipt): void
    {
        if (! $receipt->wasChanged('status') || $receipt->status !== 'approved') {
            return;
        }

        $tenantId = $this->resolveTenantId($receipt);
        if (! $tenantId) {
            return;
        }

        $forceRepost = LedgerTransaction::where('reference_type', FeeReceipt::class)
            ->where('reference_id', $receipt->id)
            ->exists();

        app(FeeReceiptLedgerDispatcher::class)->postApproved($receipt, $tenantId, $forceRepost);
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

        return $feeable->tenant_id
            ?? $feeable->school?->parent_id
            ?? null;
    }
}
