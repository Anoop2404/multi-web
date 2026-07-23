<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;

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

    public function canAdminCancelWithRefund(FestRegistration $registration, FestEvent $event): bool
    {
        if (in_array($registration->status, ['withdrawn', 'rejected'], true)) {
            return false;
        }

        if ($event->results_published) {
            return false;
        }

        // The whole point of this path is the case plain cancel() blocks: an approved
        // payment already exists. If there's no approved payment, canAdminCancel()/cancel()
        // already handles it — no reason to route through here.
        return app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration);
    }

    /**
     * Explicit, admin-initiated cancellation of a registration that already has an approved
     * payment against it — the case plain cancel() deliberately refuses (see docs/
     * FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §4/§9.4). Does NOT touch FeeReceiptReversalService
     * or reverse any receipt (a receipt commonly funds several items at once — reversing it
     * would wipe out payment status for other, still-valid registrations). Instead it reuses
     * the same fee-model-agnostic delta technique as FestRegistrationBulkService::rejectMany()
     * (§9.2): measure what cancelling this one registration reduces total_due by, and record
     * that as a FestFeeCredit rather than silently leaving the school overpaid.
     *
     * Also frees the chest number and deletes any marks recorded against this registration's
     * participants — cancel() (the pre-payment path) never had to worry about either because
     * a registration that's never been paid/approved essentially never has marks or a revealed
     * chest number yet; this path can be reached later in the lifecycle, so both are handled
     * explicitly. Still blocked once results are published — reversing a *published* result is
     * a bigger integrity question than this fix is scoped to answer.
     */
    public function cancelWithRefund(FestRegistration $registration, FestEvent $event, string $reason, bool $notify = true): void
    {
        abort_if($registration->event_id !== $event->id, 422);
        abort_unless(trim($reason) !== '', 422, 'A reason is required to cancel a paid, approved registration.');
        abort_unless($this->canAdminCancelWithRefund($registration, $event), 422,
            'This registration cannot be cancelled with refund — it is already closed, results are published, or it was never paid.');

        $feeService = app(FestSchoolEventFeeService::class);

        $registration->loadMissing('item', 'participants');
        $headId = $registration->item?->head_id;
        $participantIds = $registration->participants->pluck('id');

        $feeBefore = $feeService->currentFeeRecordFor($event, $registration->school_id);
        $dueBefore = (float) ($feeBefore?->total_due ?? 0);
        $paidBefore = (float) ($feeBefore?->amount_paid ?? 0);

        $registration->update(['status' => 'withdrawn']);

        // Free the chest number and drop any marks — this registration is no longer a
        // competing entry. Deleting (not orphaning) marks avoids a cancelled participant's
        // score lingering in any not-yet-published scoreboard calculation.
        if ($participantIds->isNotEmpty()) {
            FestMark::whereIn('participant_id', $participantIds)->delete();
            FestParticipant::whereIn('id', $participantIds)->update(['chest_no' => null]);
        }

        $feeAfter = $feeService->recalculate($event, $registration->school_id);

        $reduction = round($dueBefore - (float) $feeAfter->total_due, 2);
        if ($reduction > 0 && $paidBefore > 0) {
            $credit = FestFeeCredit::create([
                'fest_school_event_fee_id' => $feeAfter->id,
                'source_registration_id' => $registration->id,
                'amount' => min($reduction, $paidBefore),
                'reason' => 'Registration cancelled after payment: '.$reason,
                'created_by_user_id' => auth()->id(),
            ]);

            app(PlatformAuditLogger::class)->log(
                action: 'fest_fee_credit.issued',
                description: "Fee credit of ₹{$credit->amount} issued — registration #{$registration->id} cancelled after payment ({$reason})",
                subject: $credit,
                properties: [
                    'event_id' => $event->id,
                    'school_id' => $registration->school_id,
                    'registration_id' => $registration->id,
                    'amount' => (float) $credit->amount,
                ],
                category: 'finance',
            );
        }

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        app(PlatformAuditLogger::class)->festRegistrationCancelled($registration);

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

    /**
     * Unlike canSchoolCancel(), this deliberately does NOT check for approved payment —
     * editing the roster in place (not withdrawing it) is allowed even after payment is
     * approved, as long as the edit doesn't reduce what's owed. The caller (updateForSchool())
     * is responsible for comparing the fee before/after the edit and rejecting any change that
     * would decrease total_due, since a decrease would need a refund/credit path this method
     * knows nothing about — see FestRegistrationCreateService::updateForSchool().
     */
    public function canSchoolEditRoster(FestRegistration $registration, FestEvent $event): bool
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

        return $event->isRegistrationOpen();
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
