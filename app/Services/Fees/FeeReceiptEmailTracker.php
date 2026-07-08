<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;

class FeeReceiptEmailTracker
{
    public function markQueued(FeeReceipt $receipt): void
    {
        $receipt->update([
            'receipt_email_status' => 'queued',
            'receipt_email_error'  => null,
        ]);
    }

    public function markSent(FeeReceipt $receipt): void
    {
        $receipt->update([
            'receipt_emailed_at'     => now(),
            'receipt_email_status'   => 'sent',
            'receipt_email_error'    => null,
        ]);
    }

    public function markFailed(FeeReceipt $receipt, string $error): void
    {
        $receipt->update([
            'receipt_email_status' => 'failed',
            'receipt_email_error'  => mb_substr($error, 0, 2000),
        ]);
    }

    public function markSkipped(FeeReceipt $receipt, string $reason): void
    {
        $receipt->update([
            'receipt_email_status' => 'skipped',
            'receipt_email_error'  => mb_substr($reason, 0, 2000),
        ]);
    }

    public function incrementResend(FeeReceipt $receipt): void
    {
        $receipt->increment('receipt_email_resend_count');
    }
}
