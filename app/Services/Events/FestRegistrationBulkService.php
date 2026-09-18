<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use Illuminate\Support\Facades\DB;

class FestRegistrationBulkService
{
    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function approveMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false, ?int $itemId = null): array
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

        $query = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $event->reportableItemIds([$itemId])));

        foreach ($query->with(['participants', 'item', 'event'])->get() as $registration) {
            // Each registration in a bulk batch can belong to a different item, so the
            // item-level freeze (EventLifecycleGate::assertItemRosterNotFrozen()) can't be
            // checked once for the whole batch like the event-level call above — it must be
            // evaluated per registration. Skipped (not aborted) so one already-published
            // item doesn't block the rest of an otherwise-valid batch.
            if (! $overrideLifecycle && $registration->item?->results_published_at) {
                $errors[] = "Registration #{$registration->id}: this item's results are already published.";
                $skipped++;

                continue;
            }

            if (! $overrideLifecycle && ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event)) {
                if (! $feeService->isPaidForRegistration($event, $registration)) {
                    $feeLabel = $feeService->usesPerHeadBilling($event) ? 'Event Head fee' : 'Event fee';
                    $errors[] = "Registration #{$registration->id}: {$feeLabel} not approved.";
                    $skipped++;

                    continue;
                }
            }

            // Locks the registration row for the duration of the status flip, matching
            // rejectMany()'s locking discipline — without it, this bulk action racing a
            // concurrent single-registration approve() (or another overlapping bulk call) on
            // the same row can both pass the earlier 'submitted' filter and both run the
            // approval side effects (chest-number/participant-number assignment), since
            // neither has committed yet when the other reads. Re-checks status inside the
            // lock so a row already claimed by a concurrent action is skipped, not
            // double-approved. Notifier/audit calls stay outside the lock/transaction, same
            // reasoning as rejectMany()'s comment on that.
            $stillPending = DB::transaction(function () use ($registration, $approvalService) {
                $locked = FestRegistration::whereKey($registration->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'submitted') {
                    return false;
                }

                $approvalService->approve($registration);

                return true;
            });

            if (! $stillPending) {
                $skipped++;

                continue;
            }

            $notifier->registrationApproved($registration);
            $audit->festRegistrationApproved($registration);
            $approved++;
        }

        return ['approved' => $approved, 'rejected' => 0, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** @return array{approved: int, rejected: int, skipped: int, errors: list<string>} */
    public function rejectMany(FestEvent $event, array $registrationIds, ?int $schoolId = null, bool $overrideLifecycle = false, ?int $itemId = null, string $reason = ''): array
    {
        EventLifecycleGate::allowRegistrationReview($event, $overrideLifecycle);

        $rejected = 0;
        $skipped = 0;
        $errors = [];

        $feeService = app(FestSchoolEventFeeService::class);
        $notifier = app(FestEventNotifier::class);
        $audit = app(PlatformAuditLogger::class);

        $query = FestRegistration::whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'submitted')
            ->when($registrationIds !== [], fn ($q) => $q->whereIn('id', $registrationIds))
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($itemId, fn ($q) => $q->whereIn('item_id', $event->reportableItemIds([$itemId])));

        // Bulk actions can be invoked (e.g. via Portal duty routes) with $event resolved to
        // a region CHILD, not just the Sahodaya-admin hub — recalculate() always persists
        // the fee record under the HUB's event_id (see FestSchoolEventFeeService::
        // feeOwnerEvent()), so the lock below must target that same id or it silently locks
        // a row that never exists for a partitioned child.
        $feeOwnerEventId = $feeService->feeOwnerEvent($event)->id;

        foreach ($query->with(['participants', 'item'])->get() as $registration) {
            // See the identical per-registration check in approveMany() above for why this
            // can't be a single check up front — each registration here can belong to a
            // different item.
            if (! $overrideLifecycle && $registration->item?->results_published_at) {
                $errors[] = "Registration #{$registration->id}: this item's results are already published.";
                $skipped++;

                continue;
            }

            // Only the DB-mutating snapshot/update/credit critical section is locked and
            // transactional — notifier/audit calls happen after commit, outside the lock, so a
            // slow mail/notification dispatch never holds the row lock open. See
            // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.4.
            DB::transaction(function () use ($event, $registration, $feeService, $reason, $feeOwnerEventId) {
                // Lock the school's aggregate fee record (if one exists yet) for the duration
                // of the before/after snapshot below, so two reject/cancel actions racing on
                // the same school can't both read the same "before" state and either compute
                // a wrong delta or double-issue a credit. Must match currentFeeRecordFor()'s
                // own scoping exactly (the extra registration_batch_id filter for phased
                // events) — without it, whereNull('head_id') alone matches BOTH the rollup
                // row AND every individual payment-level row (they all have head_id = null
                // too), so ->first() could lock an arbitrary level row instead of the rollup
                // the before/after snapshot and credit creation actually read/write, leaving
                // the race this lock exists to prevent unprotected for phased-billing events.
                FestSchoolEventFee::where('event_id', $feeOwnerEventId)
                    ->where('school_id', $registration->school_id)
                    ->whereNull('head_id')
                    ->when($event->usesPhasedRegionalBilling(), fn ($q) => $q->whereNull('registration_batch_id'))
                    ->lockForUpdate()
                    ->first();

                // Snapshot the fee record before rejecting, so we can measure what this
                // rejection actually reduced total_due by — fee-model-agnostic, so it works
                // the same for flat/tiered/per-item/composite billing. See
                // docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.2 for the full rationale.
                $feeBefore = $feeService->currentFeeRecordFor($event, $registration->school_id);
                $dueBefore = (float) ($feeBefore?->total_due ?? 0);
                $paidBefore = (float) ($feeBefore?->amount_paid ?? 0);

                $registration->update([
                    'status'               => 'rejected',
                    'rejection_reason'     => $reason ?: null,
                    'rejected_at'          => now(),
                    'rejected_by_user_id'  => auth()->id(),
                ]);

                // Free up any chest number already assigned before rejection — same fix as
                // FestRegistrationReviewController::reject()'s single-registration path and
                // FestRegistrationService::cancel(); without it the number stays occupied
                // (fest_participants_event_head_chest_unique still enforces it) even though
                // the Chest Numbers admin list hides rejected registrations, so it looks
                // free on screen but can't actually be reassigned.
                FestParticipant::whereIn('id', $registration->participants->pluck('id'))->update(['chest_no' => null]);

                // Free up the per-student registration fee if this was the student's last
                // active item — must run BEFORE recalculate() so the composite fee model sees
                // it. See FestLevelRegistrationService::deactivateIfNoActiveItems().
                $levelService = app(FestLevelRegistrationService::class);
                foreach ($registration->participants->pluck('student_id')->filter()->unique() as $studentId) {
                    $levelService->deactivateIfNoActiveItems($event, $studentId);
                }

                $feeService->recalculate($event, $registration->school_id);
            });

            // LIFE-06 fix — see FestQualificationService::revokeQualificationsForRegistration().
            app(FestQualificationService::class)->revokeQualificationsForRegistration($registration);

            $notifier->registrationRejected($registration, $reason);
            $audit->festRegistrationRejected($registration, reason: $reason);
            $rejected++;
        }

        return ['approved' => 0, 'rejected' => $rejected, 'skipped' => $skipped, 'errors' => $errors];
    }
}
