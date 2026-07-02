<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;

/** @deprecated Use FestSchoolEventFeeService for event-level billing. */
class FestRegistrationFeeService
{
    public function __construct(
        private FestSchoolEventFeeService $schoolEventFeeService,
        private FestEventFeeResolver $feeResolver,
    ) {}

    public function amountDue(FestEvent $event, FestRegistration $registration): float
    {
        $fee = $this->schoolEventFeeService->recalculate($event, $registration->school_id);

        return (float) $fee->total_due;
    }

    public function feeRequired(FestEvent $event): bool
    {
        return $this->schoolEventFeeService->feeRequired($event);
    }

    public function isPaid(FestRegistration $registration): bool
    {
        return $this->schoolEventFeeService->isPaid(
            $registration->event,
            $registration->school_id
        );
    }
}
