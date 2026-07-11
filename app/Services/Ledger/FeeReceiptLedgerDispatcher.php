<?php

namespace App\Services\Ledger;

use App\Models\FeeReceipt;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Services\Events\FestFeeLedgerService;

class FeeReceiptLedgerDispatcher
{
    public function postApproved(FeeReceipt $receipt, string $tenantId, bool $forceRepost = false): void
    {
        match ($receipt->feeable_type) {
            MembershipPayment::class => app(LedgerService::class)->postFeeReceipt($receipt, $tenantId, $forceRepost),
            FestSchoolEventFee::class, FestRegistration::class => app(FestFeeLedgerService::class)->postApprovedReceipt($receipt, $forceRepost),
            TrainingRegistration::class, TrainingSchoolFee::class => app(TrainingFeeLedgerService::class)->postApprovedReceipt($receipt, $forceRepost),
            McqRegistration::class, McqSchoolFee::class => app(McqFeeLedgerService::class)->postApprovedReceipt($receipt, $forceRepost),
            default => null,
        };
    }
}
