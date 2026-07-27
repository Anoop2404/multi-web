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

    /** Block registration when the fee schedule requires prior payment. */
    public function assertCanRegister(FestEvent $event, Tenant $school): void
    {
        if ($this->requiredBeforeRegistration($event)) {
            abort_unless(
                $this->isSchoolFeeCleared($event, $school->id),
                422,
                'Fee payment must be approved before registering for this event.'
            );
        }
    }
}
