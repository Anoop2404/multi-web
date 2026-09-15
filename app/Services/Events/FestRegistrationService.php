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
        // Deliberately checks the ITEM's own results_published_at, not the event-wide
        // results_published flag (that flag only gates public-portal visibility of
        // results/scores/rankings — see Overview.vue's "Publish results, scores &
        // rankings on public portal" toggle — and an event can have that on for
        // already-finished items while a different item registered under it was never
        // actually held). Moved into the method that actually performs the mutation
        // (not left solely in canAdminCancel()) so it can't be bypassed by a caller
        // forgetting the separate check. See EventLifecycleGate::
        // assertItemRosterNotFrozen()'s own docblock for why an item can be published
        // independently of the whole event.
        abort_if($registration->item?->results_published_at, 422, 'This item\'s results are already published. Unpublish it first to cancel this registration.');

        $registration->loadMissing('item', 'participants');
        $headId = $registration->item?->head_id;
        $participantIds = $registration->participants->pluck('id');
        $studentIds = $registration->participants->pluck('student_id')->filter()->unique();
        $feeOwnerEventId = app(FestSchoolEventFeeService::class)->feeOwnerEvent($event)->id;

        DB::transaction(function () use ($event, $registration, $participantIds, $studentIds, $feeOwnerEventId) {
            // Lock the school's aggregate fee record for the duration of the status flip +
            // recalculate, so a concurrent cancel/reject on the same school can't interleave.
            // See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4. Must lock under the fee
            // OWNER event (the hub, for a partitioned child) — recalculate() always persists
            // the record there, so locking by $event->id directly locked a row that never
            // existed for a region child, silently defeating the lock. Also needs the same
            // registration_batch_id scoping currentFeeRecordFor() uses for phased-billing
            // events — whereNull('head_id') alone matches both the rollup row and every
            // individual payment-level row, so without it this could lock an arbitrary level
            // row instead of the one recalculate() actually reads/writes.
            FestSchoolEventFee::where('event_id', $feeOwnerEventId)
                ->where('school_id', $registration->school_id)
                ->whereNull('head_id')
                ->when($event->usesPhasedRegionalBilling(), fn ($q) => $q->whereNull('registration_batch_id'))
                ->lockForUpdate()
                ->first();

            $registration->update(['status' => 'withdrawn']);

            // Free the chest number and drop any marks — this registration is no longer a
            // competing entry.
            if ($participantIds->isNotEmpty()) {
                FestMark::whereIn('participant_id', $participantIds)->delete();
                FestParticipant::whereIn('id', $participantIds)->update(['chest_no' => null]);
            }

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
        return $this->canAdminCancel($registration, $event);
    }

    public function cancelWithRefund(FestRegistration $registration, FestEvent $event, string $reason, bool $notify = true): void
    {
        $this->cancel($registration, $event, $notify);
    }

    /**
     * A school may cancel even after its fee has been approved/paid — the withdraw()
     * controller action detects that case via hasApprovedPaymentForRegistration() and
     * routes the actual mutation through cancelWithRefund() instead of cancel(), so an
     * overpayment still gets tracked as a proper FestFeeCredit rather than silently
     * leaving the school's fee record out of sync with its (now smaller) roster.
     */
    public function canSchoolCancel(FestRegistration $registration, FestEvent $event): bool
    {
        if (! in_array($registration->status, ['submitted', 'approved', 'pending_approval', 'waitlisted'], true)) {
            return false;
        }

        if (in_array($event->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        // Gate purely on THIS item's own results_published_at, not the event-wide
        // results_published flag — matching every sibling guard in this class
        // (canSchoolEditRoster, canAdminCancel, substitutePerformer, addParticipant,
        // removeParticipant). This one was missed when those were fixed: once ANY item in
        // the event had its results published, a school could no longer cancel a
        // registration for a completely different, still-open item — exactly the report
        // that surfaced this (event run without phases, some items conducted and
        // published, registration reopened for the rest, and cancel silently stopped
        // working event-wide).
        if ($registration->item?->results_published_at) {
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

        // Gate purely on THIS item's own results_published_at, not the event-wide
        // results_published flag — matching every sibling guard in this class
        // (canSchoolCancel, canAdminCancel, substitutePerformer, addParticipant,
        // removeParticipant, allowRegistrationForItem/Review — see
        // FestRegistrationItemLockTest.php). The event-wide flag used to block this one
        // too, so once ANY item in the event had its results published, a school could
        // no longer edit its roster for a completely different, still-open item.
        if ($registration->item?->results_published_at) {
            return false;
        }

        // "Block new registrations" is meant to freeze rosters too (e.g. before chest
        // numbers/printing) — previously only allowRegistration() (new submissions)
        // checked this, so an already-approved roster stayed editable regardless.
        if ($event->registration_locked) {
            return false;
        }

        return $event->isRegistrationOpen();
    }

    public function canAdminCancel(FestRegistration $registration, FestEvent $event): bool
    {
        if (in_array($registration->status, ['withdrawn', 'rejected'], true)) {
            return false;
        }

        // See cancel()'s matching check for why this is the item's own
        // results_published_at, not the event-wide (public-portal) results_published flag.
        if ($registration->item?->results_published_at) {
            return false;
        }

        return true;
    }

    /**
     * Swap a performer with a standby on the same registration (pre-stage emergency).
     * Previously had no lifecycle check at all — every other roster-write action in this
     * file blocks once the event or item is published, but a substitution could still
     * silently change who the recorded winner/participant actually was after the fact.
     * Gated on this item's own results_published_at only, not the event-wide flag — same
     * reasoning as addParticipant()/removeParticipant() above.
     */
    public function substitutePerformer(FestParticipant $performer, FestParticipant $standby): void
    {
        abort_if($performer->registration_id !== $standby->registration_id, 422, 'Participants must belong to the same registration.');
        abort_if($standby->participant_role !== 'standby', 422, 'Target must be a standby.');
        abort_if($performer->participant_role === 'standby', 422, 'Cannot substitute a standby performer.');
        abort_if($performer->registration?->item?->results_published_at, 422, 'This item\'s results are already published. Unpublish it first to substitute participants.');

        // Chest number/order number belong to whoever is actually performing — leaving
        // them on the demoted participant left a standby still holding a live chest
        // number (and showing up on the Chest Numbers page as if performing; see
        // FestChestNumberController's participant_role filter added alongside this).
        // The promoted standby doesn't inherit it automatically — re-assign via the
        // Chest Numbers page (Generate/Assign Missing or manual entry), same as any
        // other participant who needs one.
        $performer->update(['participant_role' => 'standby', 'chest_no' => null, 'order_no' => null, 'chest_revealed_at' => null]);
        $standby->update(['participant_role' => 'performer']);
    }

    /**
     * Admin-direct roster edit: add a student who isn't currently on the registration at all
     * (unlike substitutePerformer(), which only swaps between two rows that already exist).
     * Deliberately does NOT check canSchoolEditRoster()/schedule_published — this is an
     * admin-only override for the exact case that lock exists to prevent schools from doing
     * themselves (day-of emergencies: sick student, no-show right before their item). Gated
     * on this item's own results_published_at only, not the event-wide results_published flag
     * — an event can have results published for other items while this one's are still open,
     * and admins need to keep managing this item's roster until its own results go out.
     */
    public function addParticipant(FestRegistration $registration, FestEvent $event, Student $student, string $role): FestParticipant
    {
        abort_unless(in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_if($registration->item?->results_published_at, 422, 'This item\'s results are already published. Unpublish it first to add a participant.');
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
     * addParticipant() above re: schedule_published vs results_published — gated on this
     * item's own results_published_at only, not the event-wide flag.
     */
    public function removeParticipant(FestParticipant $participant, FestEvent $event): void
    {
        $registration = $participant->registration;
        abort_unless($registration && in_array($registration->event_id, $event->reportableEventIds(), true), 422);
        abort_if($registration->item?->results_published_at, 422, 'This item\'s results are already published. Unpublish it first to remove a participant.');

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
