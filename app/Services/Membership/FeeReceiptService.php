<?php

namespace App\Services\Membership;

use App\Models\FeeReceipt;
use App\Models\MembershipPayment;

class FeeReceiptService
{
    public function createForMembershipPayment(MembershipPayment $payment): FeeReceipt
    {
        $receipt = FeeReceipt::create([
            'feeable_type'        => $payment->getMorphClass(),
            'feeable_id'          => $payment->id,
            'file_path'           => $payment->payment_proof_path,
            'transaction_ref'     => $payment->transaction_ref,
            'bank_name'           => $payment->payment_method,
            'payment_date'        => now()->toDateString(),
            'amount'              => $payment->amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $payment->uploaded_by_user_id,
        ]);

        $payment->update(['fee_receipt_id' => $receipt->id]);

        return $receipt;
    }

    public function syncFromMembershipPayment(MembershipPayment $payment): void
    {
        $receipt = $payment->feeReceipt;
        if (! $receipt) {
            $this->createForMembershipPayment($payment);

            return;
        }

        $receipt->update([
            'file_path'       => $payment->payment_proof_path,
            'transaction_ref' => $payment->transaction_ref,
            'bank_name'       => $payment->payment_method,
            'amount'          => $payment->amount,
            'status'          => match ($payment->status) {
                'verified' => 'approved',
                'rejected' => 'rejected',
                default    => 'uploaded',
            },
            'rejection_reason' => $payment->rejection_reason,
            'reviewed_by'      => $payment->verified_by_user_id,
            'reviewed_at'      => $payment->verified_at,
        ]);

        if ($receipt->status === 'approved') {
            $school = $payment->school ?? \App\Models\Tenant::find($payment->school_id);
            $sahodayaId = $school?->parent_id;
            if ($sahodayaId) {
                app(\App\Services\Ledger\LedgerService::class)->postFeeReceipt($receipt->fresh(), $sahodayaId);
            }
        }
    }
}
