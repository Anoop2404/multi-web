<?php

namespace App\Services\Events;

use App\Models\AccountHead;
use App\Models\FeeReceipt;
use App\Models\FestRegistration;
use App\Models\LedgerTransaction;

class FestFeeLedgerService
{
    public function postApprovedReceipt(FeeReceipt $receipt): ?LedgerTransaction
    {
        if ($receipt->status !== 'approved') {
            return null;
        }

        if ($receipt->feeable_type !== FestRegistration::class) {
            return null;
        }

        $existing = LedgerTransaction::where('reference_type', FeeReceipt::class)
            ->where('reference_id', $receipt->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $registration = FestRegistration::with('event')->find($receipt->feeable_id);
        if (! $registration?->event) {
            return null;
        }

        $sahodayaId = $registration->event->tenant_id;

        $head = AccountHead::firstOrCreate(
            ['tenant_id' => $sahodayaId, 'code' => 'EVENT-FEE'],
            ['name' => 'Event Registration Fees', 'type' => 'income', 'is_active' => true]
        );

        return LedgerTransaction::create([
            'tenant_id'        => $sahodayaId,
            'account_head_id'  => $head->id,
            'reference_type'   => FeeReceipt::class,
            'reference_id'     => $receipt->id,
            'entry_type'       => 'credit',
            'amount'           => $receipt->amount,
            'description'      => "Event fee — registration #{$registration->id}",
            'transaction_date' => $receipt->payment_date ?? now()->toDateString(),
            'posted_by'        => $receipt->reviewed_by,
        ]);
    }
}
