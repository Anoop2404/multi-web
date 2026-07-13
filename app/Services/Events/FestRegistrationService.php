<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;

class FestRegistrationService
{
    public function cancel(FestRegistration $registration, FestEvent $event, bool $notify = true): void
    {
        abort_if($registration->event_id !== $event->id, 422);
        abort_if(in_array($registration->status, ['withdrawn', 'rejected'], true), 422, 'Registration is already closed.');
        abort_if(
            app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration),
            422,
            'This registration\'s fee has already been paid and approved — it can no longer be cancelled.',
        );

        $registration->loadMissing('item');
        $headId = $registration->item?->head_id;

        $registration->update(['status' => 'withdrawn']);

        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        if ($notify) {
            app(FestEventNotifier::class)->registrationWithdrawn($registration);
        }
    }

    public function canSchoolCancel(FestRegistration $registration, FestEvent $event): bool
    {
        if (! in_array($registration->status, ['submitted', 'approved', 'pending_approval', 'waitlisted'], true)) {
            return false;
        }

        if (in_array($event->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ($event->results_published) {
            return false;
        }

        if (app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration)) {
            return false;
        }

        return $event->isRegistrationOpen() || $registration->status === 'submitted';
    }

    public function canAdminCancel(FestRegistration $registration, FestEvent $event): bool
    {
        if (in_array($registration->status, ['withdrawn', 'rejected'], true)) {
            return false;
        }

        if ($event->results_published) {
            return false;
        }

        return ! app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration);
    }

    /** Swap a performer with a standby on the same registration (pre-stage emergency). */
    public function substitutePerformer(FestParticipant $performer, FestParticipant $standby): void
    {
        abort_if($performer->registration_id !== $standby->registration_id, 422, 'Participants must belong to the same registration.');
        abort_if($standby->participant_role !== 'standby', 422, 'Target must be a standby.');
        abort_if($performer->participant_role === 'standby', 422, 'Cannot substitute a standby performer.');

        $performer->update(['participant_role' => 'standby']);
        $standby->update(['participant_role' => 'performer']);
    }
}
