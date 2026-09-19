<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;

class FestRegistrationApprovalService
{
    /**
     * Auto-approve every submitted registration for a school in one event.
     * Used when the school's event fee is fully paid — fest no longer needs a
     * separate Sahodaya registration-approval step.
     *
     * @param  ?int  $headId  When given, only registrations for items under this Event Head are
     *                        auto-approved — used when a school pays one head's fee under
     *                        sports_composite per-head billing, so paying Athletics doesn't also
     *                        auto-approve a still-unpaid Chess registration. Omit for the old
     *                        whole-event behavior (every fee model without heads).
     * @return int Number of registrations approved.
     */
    public function approveSchoolEvent(FestEvent $event, string $schoolId, ?int $headId = null): int
    {
        $count = 0;

        $policy = app(FestParticipationPolicyService::class)->resolveForEvent($event);
        $feeService = app(FestSchoolEventFeeService::class);
        $feeRequiredBeforeApproval = ($policy['require_fee_before_approval'] ?? false) && $feeService->feeRequired($event);

        FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('school_id', $schoolId)
            ->whereIn('status', ['draft', 'submitted', 'pending_approval'])
            ->when($headId !== null, fn ($q) => $q->whereHas('item', fn ($qq) => $qq->where('head_id', $headId)))
            ->with(['item.head', 'event'])
            ->orderBy('id')
            ->get()
            ->each(function (FestRegistration $registration) use (&$count, $event, $feeService, $feeRequiredBeforeApproval) {
                // Skip (not abort the whole batch) an item whose results are already
                // published — its roster is frozen, same rule FestRegistrationReviewController
                // ::approve() enforces for a single-registration approval via
                // EventLifecycleGate::assertItemRosterNotFrozen(). Fee approval can auto-approve
                // a school's registrations across many items at once (this whole method), so a
                // per-item check here, not a single blanket check before the loop, is what's
                // needed — one already-published item shouldn't block approving the school's
                // still-open items. Previously this call path (fee approval → here) was the one
                // place that skipped the roster-freeze check the single-registration path
                // already enforces, letting a late fee approval silently re-approve/chest-number
                // a registration for an item whose results were already published.
                if ($registration->item?->results_published_at) {
                    return;
                }

                // Event Head approval_policy=manual stays in the Sahodaya review queue.
                // Falls back to the event-level policy when the item has no head (Kalotsav
                // items assigned a plain category instead — see
                // docs/KALOTSAV_ITEM_CATEGORY_REPLACES_HEAD_PLAN.md §5 #3), so approval
                // policy keeps working once heads stop being created for non-sports events.
                if ($registration->item?->head?->requiresManualApproval() || $event->requiresManualApproval()) {
                    if ($registration->status !== 'pending_approval') {
                        $registration->update(['status' => 'pending_approval']);
                    }

                    return;
                }

                // If fee approval is required before registration approval, check if fee is paid for this registration
                if ($feeRequiredBeforeApproval && ! $feeService->isPaidForRegistration($event, $registration)) {
                    if ($registration->status !== 'submitted') {
                        $registration->update(['status' => 'submitted']);
                    }

                    return;
                }

                $this->approve($registration);
                $count++;
            });

        return $count;
    }

    /**
     * Promote the oldest waitlisted registration under an Event Head into submitted/pending_approval
     * when a seat frees (withdraw/reject of an active registration).
     */
    public function promoteNextWaitlisted(FestEvent $event, ?int $headId): ?FestRegistration
    {
        if ($event->registration_locked || $event->results_published || $event->status === 'completed') {
            return null;
        }

        $next = FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'waitlisted')
            ->when($headId, fn ($q) => $q->whereHas('item', fn ($qi) => $qi->where('head_id', $headId)))
            // A withdraw/reject can free a seat under one item while the waitlist's own
            // oldest entry sits under a DIFFERENT item (any item under the same Event Head,
            // or the whole event when $headId is null) whose results are already published
            // — promoting into "submitted, awaiting review" for an item that's already been
            // competed and published makes no sense and was previously done anyway, with no
            // lifecycle check of any kind on this path. Excluding those items here means the
            // next-oldest, still-open waitlisted entry gets promoted instead.
            ->whereHas('item', fn ($qi) => $qi->whereNull('results_published_at'))
            ->with(['item.head', 'event'])
            ->orderBy('id')
            ->first();

        if (! $next || ! $next->item) {
            return null;
        }

        $limitService = new FestParticipationLimitService($event);
        if ($limitService->isHeadAtCapacity($next->item, $next->school_id)) {
            return null;
        }

        $status = ($next->item->head?->requiresManualApproval() || $event->requiresManualApproval())
            ? 'pending_approval'
            : 'submitted';

        $next->update([
            'status' => $status,
            'submitted_at' => $next->submitted_at ?? now(),
        ]);

        $next = $next->fresh(['item.head', 'participants']);
        foreach ($next->participants as $participant) {
            app(FestNumberingService::class)->assignParticipantNumbers($participant);
        }
        app(FestSchoolEventFeeService::class)->recalculate($event, $next->school_id);

        return $next;
    }

    /** Promote all waitlisted registrations for an event whose quotas are now open. */
    public function promoteAllEligibleWaitlisted(FestEvent $event): int
    {
        if ($event->registration_locked || $event->results_published || $event->status === 'completed') {
            return 0;
        }

        $waitlistedRegs = FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'waitlisted')
            ->with(['item.head', 'event'])
            ->orderBy('id')
            ->get();

        $promotedCount = 0;
        foreach ($waitlistedRegs as $reg) {
            if (! $reg->item) {
                continue;
            }

            // See promoteNextWaitlisted()'s identical exclusion for why — don't promote into
            // "submitted, awaiting review" for an item whose results are already published.
            if ($reg->item->results_published_at) {
                continue;
            }

            $limitService = new FestParticipationLimitService($event);
            if (! $limitService->isHeadAtCapacity($reg->item, $reg->school_id)) {
                $status = ($reg->item->head?->requiresManualApproval() || $event->requiresManualApproval())
                    ? 'pending_approval'
                    : 'submitted';

                $reg->update([
                    'status'       => $status,
                    'submitted_at' => $reg->submitted_at ?? now(),
                ]);

                $reg->loadMissing('participants');
                foreach ($reg->participants as $participant) {
                    app(FestNumberingService::class)->assignParticipantNumbers($participant);
                }
                app(FestSchoolEventFeeService::class)->recalculate($event, $reg->school_id);

                $promotedCount++;
            }
        }

        return $promotedCount;
    }

    public function approve(FestRegistration $registration): void
    {
        $registration->update(['status' => 'approved']);
        $registration->load(['participants', 'item', 'event']);

        $event = $registration->event;
        if (! $event) {
            return;
        }

        $levelService = app(FestLevelRegistrationService::class);
        $numbering = app(FestNumberingService::class);
        $settings = $numbering->settings($event);
        $autoAssign = (bool) ($settings['auto_assign_on_approve'] ?? true);
        // Independent of $autoAssign above (which still unconditionally governs item-reg
        // numbers): lets chest-number assignment specifically stop happening live at
        // approval time, so numbers can instead be handed out later in one shuffled batch
        // via assignMissingChestNumbers() -- see that method's docblock for why a live,
        // one-at-a-time assignment can't itself be made random without either leaving
        // gaps in the numbering or renumbering already-issued numbers later. Falls back
        // to $autoAssign when unset, so any event that has never touched this setting
        // keeps its exact current behavior.
        $autoAssignChest = array_key_exists('auto_assign_chest_on_approve', $settings)
            ? (bool) $settings['auto_assign_chest_on_approve']
            : $autoAssign;

        foreach ($registration->participants as $participant) {
            if ($participant->participant_role === 'standby') {
                continue;
            }

            $updates = ['event_id' => $event->id];

            if ($autoAssignChest) {
                if (! $numbering->persistedChestNumber($participant) && $registration->item_id && $registration->item) {
                    ['chest' => $chest, 'persist' => $persist, 'chest_head_id' => $chestHeadId] = $numbering->resolveChestAssignment(
                        $event,
                        $registration->item,
                        $participant
                    );
                    if ($persist) {
                        $updates['chest_no'] = $chest;
                        $updates['chest_head_id'] = $chestHeadId;
                    }
                }
            }

            if ($autoAssign) {
                if (! $participant->item_registration_number && $registration->item) {
                    $updates['item_registration_number'] = $numbering->nextItemRegistrationNumber($event, $registration->item);
                }
            }

            $participant->update($updates);

            if ($participant->student_id) {
                $levelService->syncParticipant($participant->fresh());
            } elseif ($participant->teacher_id) {
                $levelService->syncTeacherParticipant($participant->fresh());
            }
        }
    }
}
