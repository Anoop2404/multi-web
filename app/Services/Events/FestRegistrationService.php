<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestGroup;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Student;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Facades\DB;

class FestRegistrationService
{
    public function cancel(FestRegistration $registration, FestEvent $event, bool $notify = true): void
    {
        // Registrations for a partitioned hub are created against the school's assigned
        // region child, not the hub — a strict id match 403'd this for every such
        // registration when called with the hub (see the identical fix in
        // FestRegistrationReviewController for individual approve/reject/substitute).
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_if(in_array($registration->status, ['withdrawn', 'rejected'], true), 422, 'Registration is already closed.');
        abort_if(
            app(FestSchoolEventFeeService::class)->hasApprovedPaymentForRegistration($event, $registration),
            422,
            'This registration\'s fee has already been paid and approved — it can no longer be cancelled.',
        );
        // LIFE-04 fix (functional audit, 2026-08-11/12): this guard previously
        // only existed in canAdminCancel() — a caller (like the direct reject/
        // cancel controller actions) that didn't check that method first could
        // cancel a registration after its event's results were already
        // published. Moved into the method that actually performs the
        // mutation so it can't be bypassed by a caller forgetting the
        // separate check.
        abort_if($event->results_published, 422, 'Results have already been published for this event — this registration can no longer be cancelled.');

        $registration->loadMissing('item', 'participants');
        $headId = $registration->item?->head_id;
        $studentIds = $registration->participants->pluck('student_id')->filter()->unique();
        $feeOwnerEventId = app(FestSchoolEventFeeService::class)->feeOwnerEvent($event)->id;

        DB::transaction(function () use ($event, $registration, $studentIds, $feeOwnerEventId) {
            // Lock the school's aggregate fee record for the duration of the status flip +
            // recalculate, so a concurrent cancel/reject on the same school can't interleave.
            // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4. Must lock under the fee
            // OWNER event (the hub, for a partitioned child) — recalculate() always persists
            // the record there, so locking by $event->id directly locked a row that never
            // existed for a region child, silently defeating the lock.
            FestSchoolEventFee::where('event_id', $feeOwnerEventId)
                ->where('school_id', $registration->school_id)
                ->whereNull('head_id')
                ->lockForUpdate()
                ->first();

            $registration->update(['status' => 'withdrawn']);

            // Free up the per-student registration fee if this was the student's last active
            // item — must run BEFORE recalculate() so the composite fee model sees the
            // deactivation. See FestLevelRegistrationService::deactivateIfNoActiveItems().
            $levelService = app(FestLevelRegistrationService::class);
            foreach ($studentIds as $studentId) {
                $levelService->deactivateIfNoActiveItems($event, $studentId);
            }

            app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);
        });

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        // LIFE-06 fix: unwind any downstream qualification this registration
        // had already produced (participant won and was promoted before this
        // cancellation) — see FestQualificationService::revokeQualificationsForRegistration().
        app(FestQualificationService::class)->revokeQualificationsForRegistration($registration);

        if ($notify) {
            app(FestEventNotifier::class)->registrationWithdrawn($registration);
            try {
                app(FestEventNotifier::class)->registrationWithdrawnAdmin($registration);
            } catch (\Throwable) {
                // non-blocking — sahodaya notification failure must never roll back the cancel
            }
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
        // See cancel() above for why this can't be a strict id match.
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_unless(trim($reason) !== '', 422, 'A reason is required to cancel a paid, approved registration.');
        abort_unless($this->canAdminCancelWithRefund($registration, $event), 422,
            'This registration cannot be cancelled with refund — it is already closed, results are published, or it was never paid.');

        $feeService = app(FestSchoolEventFeeService::class);

        $registration->loadMissing('item', 'participants');
        $headId = $registration->item?->head_id;
        $participantIds = $registration->participants->pluck('id');
        $feeOwnerEventId = $feeService->feeOwnerEvent($event)->id;

        // Lock the school's aggregate fee record for the duration of the snapshot/update/
        // credit critical section, so a concurrent cancel/reject on the same school can't
        // interleave and produce a wrong delta or a duplicate credit. Notifier/audit calls
        // stay outside, after commit. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4.
        $studentIds = $registration->participants->pluck('student_id')->filter()->unique();

        $creditAmount = DB::transaction(function () use ($event, $registration, $feeService, $reason, $participantIds, $studentIds, $feeOwnerEventId) {
            // Locked under the fee OWNER event — see cancel() above for why.
            FestSchoolEventFee::where('event_id', $feeOwnerEventId)
                ->where('school_id', $registration->school_id)
                ->whereNull('head_id')
                ->lockForUpdate()
                ->first();

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

            // Free up the per-student registration fee if this was the student's last active
            // item — must run BEFORE recalculate() so the composite fee model sees it. See
            // FestLevelRegistrationService::deactivateIfNoActiveItems().
            $levelService = app(FestLevelRegistrationService::class);
            foreach ($studentIds as $studentId) {
                $levelService->deactivateIfNoActiveItems($event, $studentId);
            }

            $feeAfter = $feeService->recalculate($event, $registration->school_id);

            $reduction = round($dueBefore - (float) $feeAfter->total_due, 2);
            $creditAmount = null;
            if ($reduction > 0 && $paidBefore > 0) {
                $creditAmount = min($reduction, $paidBefore);
                $credit = FestFeeCredit::create([
                    'fest_school_event_fee_id' => $feeAfter->id,
                    'source_registration_id' => $registration->id,
                    'amount' => $creditAmount,
                    'reason' => 'Registration cancelled after payment: '.$reason,
                    'created_by_user_id' => auth()->id(),
                ]);

                // See FestRegistrationBulkService::rejectMany() for the identical hook — reduces
                // recognized income for this event and records the liability owed back to the
                // school, without touching CASH-BANK. FestFeeLedgerService::postCreditIssued().
                app(FestFeeLedgerService::class)->postCreditIssued($credit);

                try {
                    app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                } catch (\Throwable) {
                    // credit is already recorded + posted; the note can be regenerated later
                }

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

            return $creditAmount;
        });

        if ($headId) {
            app(FestRegistrationApprovalService::class)->promoteNextWaitlisted($event, (int) $headId);
        }

        // LIFE-06 fix — see cancel() above.
        app(FestQualificationService::class)->revokeQualificationsForRegistration($registration);

        app(PlatformAuditLogger::class)->festRegistrationCancelled($registration);

        // Distinct from cancel()'s notification: this one carries the required reason (and the
        // credit amount, if one was issued) so the school knows why an approved, paid entry was
        // pulled — see FestEventNotifier::registrationCancelledWithRefund().
        if ($notify) {
            app(FestEventNotifier::class)->registrationCancelledWithRefund($registration, $reason, $creditAmount);
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
        // 'rejected' included so a school can fix and resubmit instead of the only other
        // option being to abandon the row and start an unrelated new registration — see
        // Documents/Path_breaks.md. updateForSchool() resets status back to 'submitted'
        // and clears the rejection fields once a rejected registration is edited.
        if (! in_array($registration->status, ['submitted', 'approved', 'pending_approval', 'waitlisted', 'rejected'], true)) {
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

    /**
     * Admin-direct roster edit: add a student who isn't currently on the registration at all
     * (unlike substitutePerformer(), which only swaps between two rows that already exist).
     * Deliberately does NOT check canSchoolEditRoster()/schedule_published — this is an
     * admin-only override for the exact case that lock exists to prevent schools from doing
     * themselves (day-of emergencies: sick student, no-show right before their item). Still
     * blocked once results are published, same as every other admin override in this file —
     * reversing a published result is out of scope everywhere else too.
     */
    public function addParticipant(FestRegistration $registration, FestEvent $event, Student $student, string $role): FestParticipant
    {
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_if($event->results_published, 422, 'Results have already been published for this event.');
        abort_unless(in_array($role, ['performer', 'standby'], true), 422, 'Invalid role.');
        abort_if((string) $student->tenant_id !== (string) $registration->school_id, 422, "The student's school does not match this registration.");

        $registration->loadMissing('participants', 'item');
        abort_if($registration->participants->contains('student_id', $student->id), 422, 'This student is already on the registration.');

        $item = $registration->item;
        $groupId = null;
        if ($item && FestTeamSquadRules::isMultiPerson($item->participant_type)) {
            $error = $item->validateSquadCount($registration->participants->count() + 1);
            abort_if($error, 422, $error);

            // Team/group rows are grouped by group_id everywhere they're displayed (e.g.
            // FestChestNumberController::teamRows()) — without this, a newly-added member
            // has group_id null and renders as its own orphaned "team of one" instead of
            // joining the existing squad. Mirrors FestRegistrationCreateService::createForSchool(),
            // which assigns every performer/standby the same FestGroup on creation.
            $groupId = FestGroup::where('registration_id', $registration->id)->value('id')
                ?? $registration->participants->first()?->group_id
                ?? FestGroup::create(['registration_id' => $registration->id])->id;
        } else {
            // Individual items carry no FestTeamSquadRules (validateSquadCount() is always a
            // no-op for them) — cap manually at 1 performer + 2 standbys, matching the existing
            // "Standbys (optional, max 2)" convention already enforced client-side in the
            // Register-on-behalf form (Registrations.vue).
            if ($role === 'performer') {
                abort_if($registration->participants->where('participant_role', '!=', 'standby')->isNotEmpty(), 422,
                    'This item only allows one performer — remove the current performer first, or add this student as a standby.');
            } else {
                abort_if($registration->participants->where('participant_role', 'standby')->count() >= 2, 422, 'At most 2 standbys are allowed.');
            }
        }

        $participant = DB::transaction(function () use ($registration, $event, $student, $role, $groupId) {
            $participant = FestParticipant::create([
                'registration_id'  => $registration->id,
                'group_id'         => $groupId,
                'event_id'         => $event->id,
                'student_id'       => $student->id,
                'participant_type' => 'student',
                'participant_role' => $role,
            ]);

            app(FestNumberingService::class)->assignParticipantNumbers($participant);
            app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

            return $participant;
        });

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            'registrations',
            'fest.registration.participant_added',
            "Added {$student->name} ({$role}) to registration #{$registration->id}",
            ['registration_id' => $registration->id, 'student_id' => $student->id, 'role' => $role],
        );

        return $participant;
    }

    /**
     * Admin-direct roster edit: remove a participant outright. Hard-deletes the row — this
     * codebase has no soft-delete convention for participants (disqualified_at is a distinct
     * misconduct concept, not roster removal); FestRegistrationCreateService::updateForSchool()
     * already hard-deletes as part of a full roster replace. Same admin-override posture as
     * addParticipant() above re: schedule_published vs results_published.
     */
    public function removeParticipant(FestParticipant $participant, FestEvent $event): void
    {
        $registration = $participant->registration;
        abort_unless($registration && in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_if($event->results_published, 422, 'Results have already been published for this event.');

        $registration->loadMissing('participants');
        abort_if($registration->participants->count() <= 1, 422, 'Cannot remove the last participant on a registration — cancel the registration instead.');

        $schoolId = $registration->school_id;
        $registrationId = $registration->id;
        $participantId = $participant->id;
        $label = $participant->student?->name ?? $participant->teacher?->name ?? "participant #{$participantId}";

        DB::transaction(function () use ($participant, $event, $schoolId) {
            FestMark::where('participant_id', $participant->id)->delete();
            $participant->delete();
            app(FestSchoolEventFeeService::class)->recalculate($event, $schoolId);
        });

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            'registrations',
            'fest.registration.participant_removed',
            "Removed {$label} from registration #{$registrationId}",
            ['registration_id' => $registrationId, 'participant_id' => $participantId],
        );
    }
}
