<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;

class FestRegistrationFeeGate
{
    public function __construct(
        private FestSchoolEventFeeService $feeService,
    ) {}

    public function requiredBeforeRegistration(FestEvent $event): bool
    {
        if (! $this->feeService->feeRequired($event)) {
            return false;
        }

        $schedule = $this->feeService->resolveSchedule($event);

        return (bool) ($schedule['require_fee_before_registration'] ?? ($event->event_type === 'sports'));
    }

    public function isSchoolFeeCleared(FestEvent $event, string $schoolId): bool
    {
        if (! $this->feeService->feeRequired($event)) {
            return true;
        }

        return $this->feeService->isPaid($event, $schoolId);
    }

    /** Registration is allowed before fee verification; downloads are gated separately. */
    public function assertCanRegister(FestEvent $event, Tenant $school): void
    {
        // No-op — schools may register before Sahodaya verifies event fees.
    }
}
