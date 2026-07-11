<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;

class FestRegistrationBulkService
{
    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function approveMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false): array
    {
        EventLifecycleGate::allowRegistrationReview($event, $overrideLifecycle);

        $approved = 0;
        $skipped = 0;
        $errors = [];

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);
        $approvalService = app(FestRegistrationApprovalService::class);
        $notifier = app(FestEventNotifier::class);
        $audit = app(PlatformAuditLogger::class);

        $query = FestRegistration::where('event_id', $event->id)
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        foreach ($query->with(['participants', 'item', 'event'])->get() as $registration) {
            if (($policy['require_fee_before_approval'] ?? true) && $feeService->feeRequired($event)) {
                if (! $feeService->isPaidForRegistration($event, $registration)) {
                    $errors[] = "Registration #{$registration->id}: Event Head fee not approved.";
                    $skipped++;

                    continue;
                }
            }

            $approvalService->approve($registration);
            $notifier->registrationApproved($registration);
            $audit->festRegistrationApproved($registration);
            $approved++;
        }

        return ['approved' => $approved, 'rejected' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function rejectMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false): array
    {
        EventLifecycleGate::allowRegistrationReview($event, $overrideLifecycle);

        $rejected = 0;
        $skipped = 0;
        $errors = [];

        $feeService = app(FestSchoolEventFeeService::class);
        $notifier = app(FestEventNotifier::class);
        $audit = app(PlatformAuditLogger::class);

        $query = FestRegistration::where('event_id', $event->id)
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        foreach ($query->get() as $registration) {
            $registration->update(['status' => 'rejected']);
            $feeService->recalculate($event, $registration->school_id);
            $notifier->registrationRejected($registration);
            $audit->festRegistrationRejected($registration);
            $rejected++;
        }

        return ['approved' => 0, 'rejected' => $rejected, 'skipped' => $skipped, 'errors' => $errors];
    }
}
