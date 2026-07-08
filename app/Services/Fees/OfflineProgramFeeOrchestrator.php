<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\Tenant;

/**
 * Common post-approval steps for program fee receipts (fest, MCQ, training).
 */
class OfflineProgramFeeOrchestrator
{
    public function __construct(
        private ProgramFeeReceiptMailer $mailer,
    ) {}

    public function notifyApproved(
        Tenant $school,
        FeeReceipt $receipt,
        string $feeTypeLabel,
        string $contextTitle,
        ?string $receiptHtml = null,
        string $adminPath = 'payments',
    ): bool {
        return $this->mailer->sendApproved(
            $school,
            $receipt->fresh(),
            $feeTypeLabel,
            $contextTitle,
            $receiptHtml,
            $adminPath,
        );
    }
}
