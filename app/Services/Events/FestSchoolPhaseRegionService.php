<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\FestEventItem;
use App\Models\FestAttendance;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FestSchoolPhaseRegionService
{
    public function select(
        FestEvent $event,
        FestEventPhase $phase,
        string $schoolId,
        int $regionId,
        ?int $actorId = null,
        bool $override = false,
        ?string $reason = null,
        bool $acknowledgePaidInvoice = false,
    ): FestSchoolPhaseRegionSelection {
        $root = $event->rootEvent();
        abort_unless($root->workflow_mode === FestPhasedWorkflowService::MODE, 422, 'This event does not use phase-specific region selection.');
        abort_unless($phase->event_id === $root->id && $phase->isRegional(), 422, 'Select a region only for a regional source phase.');

        $allowed = FestPhaseRegion::where('phase_id', $phase->id)
            ->where('region_id', $regionId)
            ->where('enabled', true)
            ->first();
        abort_if(! $allowed, 422, 'That region is not enabled for this phase.');

        // A school with an already-paid invoice can be moved with $override=true (that
        // flag only bypasses the lock-after-registration-started check above) with zero
        // warning otherwise -- migrateRegistrations() itself never touches amount_paid,
        // but the invoice's total_due/line items stay frozen on the OLD registrations
        // ("paid invoices are immutable", see FestRegistrationBatchFeeService::
        // recalculateBatch()) until someone explicitly runs a forced recalculation. Make
        // that consequence something the admin has to actively acknowledge, not a silent
        // side effect they discover later as "the billing looks wrong."
        if ($override && ! $acknowledgePaidInvoice) {
            $paidTotal = (float) FestSchoolEventFee::where('event_id', $root->id)
                ->where('school_id', $schoolId)
                ->where('amount_paid', '>', 0)
                ->sum('amount_paid');
            if ($paidTotal > 0) {
                throw ValidationException::withMessages([
                    'region_id' => sprintf(
                        'This school has already paid ₹%s toward this event. Switching regions will not update that invoice automatically -- run fest:recalculate-batch-billing afterward to reconcile it. Confirm to proceed anyway.',
                        number_format($paidTotal, 2),
                    ),
                ]);
            }
        }

        return DB::transaction(function () use ($root, $phase, $schoolId, $regionId, $actorId, $override, $reason, $allowed, $acknowledgePaidInvoice) {
            $selection = FestSchoolPhaseRegionSelection::where('event_id', $root->id)
                ->where('phase_id', $phase->id)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->first();

            if ($selection && $selection->region_id !== $regionId && $selection->isLocked() && ! $override) {
                throw ValidationException::withMessages([
                    'region_id' => 'This phase region is locked because registration has started. Ask an administrator for an audited override.',
                ]);
            }

            if ($allowed->capacity !== null && (! $selection || $selection->region_id !== $regionId)) {
                $used = FestSchoolPhaseRegionSelection::where('event_id', $root->id)
                    ->where('phase_id', $phase->id)
                    ->where('region_id', $regionId)
                    ->count();
                abort_if($used >= $allowed->capacity, 422, 'That region has reached its school capacity.');
            }

            $oldRegionId = $selection?->region_id;
            $changed = $selection && (int) $selection->region_id !== $regionId;
            $selection ??= new FestSchoolPhaseRegionSelection([
                'event_id' => $root->id,
                'phase_id' => $phase->id,
                'school_id' => $schoolId,
                'selected_at' => now(),
                'selected_by' => $actorId,
                // Locked the moment a school makes its first pick for this phase, not
                // deferred until their first registration (lockForRegistration() below) —
                // a region choice must not be self-service-changeable at all once made; only
                // the audited admin override ($override above) can move a school afterward.
                'locked_at' => now(),
            ]);

            $selection->fill([
                'region_id' => $regionId,
                'changed_at' => $changed ? now() : $selection->changed_at,
                'changed_by' => $changed ? $actorId : $selection->changed_by,
                'change_reason' => $changed ? $reason : $selection->change_reason,
            ]);
            $selection->save();

            if ($changed && $override) {
                $this->migrateRegistrations($root, $phase, $schoolId, (int) $oldRegionId, $regionId, $acknowledgePaidInvoice);
            }

            return $selection->fresh(['phase', 'region']);
        });
    }

    public function resolve(FestEvent $event, FestEventPhase $phase, string $schoolId): ?FestSchoolPhaseRegionSelection
    {
        $root = $event->rootEvent();
        $sourcePhaseId = $phase->source_phase_id ?: $phase->id;

        return FestSchoolPhaseRegionSelection::where('event_id', $root->id)
            ->where('phase_id', $sourcePhaseId)
            ->where('school_id', $schoolId)
            ->first();
    }

    public function requireSelection(FestEvent $event, FestEventPhase $phase, string $schoolId): FestSchoolPhaseRegionSelection
    {
        $selection = $this->resolve($event, $phase, $schoolId);
        if (! $selection) {
            throw ValidationException::withMessages([
                'region_id' => "Select a region for {$phase->name} before registering students.",
            ]);
        }

        return $selection;
    }

    public function lockForRegistration(FestEvent $event, FestEventPhase $phase, string $schoolId): void
    {
        $sourcePhaseId = $phase->source_phase_id ?: $phase->id;
        $sourcePhase = $phase->source_phase_id ? FestEventPhase::find($sourcePhaseId) : $phase;

        if (! $sourcePhase || ! $sourcePhase->isRegional()) {
            return;
        }

        $selection = $this->requireSelection($event, $sourcePhase, $schoolId);
        if (! $selection->locked_at) {
            $selection->update(['locked_at' => now()]);
        }
    }

    public function operationalEvent(FestEvent $event, FestEventPhase $phase, string $schoolId): FestEvent
    {
        $root = $event->rootEvent();
        $sourcePhaseId = $phase->source_phase_id ?: $phase->id;
        $sourcePhase = $phase->source_phase_id ? FestEventPhase::findOrFail($sourcePhaseId) : $phase;
        $regionId = $sourcePhase->isRegional()
            ? $this->requireSelection($root, $sourcePhase, $schoolId)->region_id
            : null;

        $leaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $sourcePhaseId)
            ->where('region_id', $regionId)
            ->first();

        if (! $leaf) {
            app(FestPhaseTopologyService::class)->sync($root);
            $leaf = FestEvent::where('parent_event_id', $root->id)
                ->where('source_phase_id', $sourcePhaseId)
                ->where('region_id', $regionId)
                ->first();
        }

        abort_if(! $leaf, 422, 'The operational event for this phase and region is not configured.');

        return $leaf;
    }

    public function hasRegistrations(FestEvent $event, FestEventPhase $phase, string $schoolId): bool
    {
        $root = $event->rootEvent();

        return FestRegistration::where('school_id', $schoolId)
            ->whereIn('event_id', FestEvent::where('parent_event_id', $root->id)
                ->where('source_phase_id', $phase->source_phase_id ?: $phase->id)
                ->pluck('id'))
            ->exists();
    }

    private function migrateRegistrations(
        FestEvent $root,
        FestEventPhase $phase,
        string $schoolId,
        int $oldRegionId,
        int $newRegionId,
        bool $forceFeeRecalc = false,
    ): void {
        $oldLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $oldRegionId)
            ->first();
        if (! $oldLeaf) {
            return;
        }

        $newLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->where('region_id', $newRegionId)
            ->first();
        if (! $newLeaf) {
            app(FestPhaseTopologyService::class)->sync($root);
            $newLeaf = FestEvent::where('parent_event_id', $root->id)
                ->where('source_phase_id', $phase->id)
                ->where('region_id', $newRegionId)
                ->firstOrFail();
        }

        $registrations = FestRegistration::where('event_id', $oldLeaf->id)
            ->where('school_id', $schoolId)
            ->with(['item', 'participants'])
            ->get();
        $participantIds = $registrations->flatMap->participants->pluck('id');
        abort_if(
            FestMark::whereIn('participant_id', $participantIds)->exists()
                || FestAttendance::whereIn('participant_id', $participantIds)->exists()
                || FestSchedule::whereIn('participant_id', $participantIds)->exists(),
            422,
            'This region cannot be changed after attendance, scheduling, or mark entry has started.'
        );

        foreach ($registrations as $registration) {
            $rootItemId = $registration->item?->inherited_from_item_id;
            // A null inherited_from_item_id means this item was authored directly on the
            // OLD leaf, never copied down from a hub item via FestItemSyncService --
            // there is no reliable way to know which item on the new leaf corresponds to
            // it. ->where('inherited_from_item_id', null) would silently become
            // whereNull() and ->first() would pick an arbitrary un-inherited item on the
            // new leaf, reassigning a registration (possibly already paid for) to a
            // completely different competition item with a different fee. Refuse instead.
            abort_if($rootItemId === null, 422, "\"{$registration->item?->title}\" isn't linked to a shared catalog item, so it can't be safely matched to an item in the new region. Fix the item's catalog link first.");

            $targetItem = FestEventItem::where('event_id', $newLeaf->id)
                ->where('inherited_from_item_id', $rootItemId)
                ->first();
            abort_if(! $targetItem, 422, 'The selected region is missing a registered item. Synchronize topology and try again.');

            // The new leaf may already have its own active registration for this exact
            // school+item (e.g. the school registered there directly before the switch,
            // or a previous switch attempt partially applied before this fix). Postgres
            // enforces a unique (event_id, school_id, item_id) index for non-withdrawn/
            // rejected rows, so blindly re-pointing into that would throw a raw
            // constraint-violation exception mid-loop. Surface it clearly instead, naming
            // the conflicting registration so an admin can resolve it deliberately.
            $collision = FestRegistration::where('event_id', $newLeaf->id)
                ->where('school_id', $schoolId)
                ->where('item_id', $targetItem->id)
                ->whereNotIn('status', ['withdrawn', 'rejected'])
                ->first();
            abort_if($collision, 422, "This school already has an active registration (#{$collision?->id}) for \"{$targetItem->title}\" in the target region. Resolve or withdraw that registration before switching.");

            $registration->update(['event_id' => $newLeaf->id, 'item_id' => $targetItem->id]);
            // migrateRegistrations() only ever moved the registration itself -- its
            // participants (fest_participants.event_id, the scope column chest-number
            // uniqueness is enforced against) silently stayed on the old leaf, unlike
            // FestRegionRoundMigrationService's migration which updates both.
            FestParticipant::where('registration_id', $registration->id)->update(['event_id' => $newLeaf->id]);
            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));
        }

        // Without force: true, recalculateBatch() deliberately leaves a paid fee record's
        // total_due/line items untouched ("paid invoices are immutable" -- protects
        // against silently rewriting financial history on an ordinary registration
        // edit). A region switch is not an ordinary edit: the admin already explicitly
        // acknowledged the paid invoice above to get here at all, so the whole point of
        // that acknowledgement is that the invoice SHOULD now be corrected to match
        // where the registrations actually ended up -- not left stale, requiring a
        // developer to separately run fest:recalculate-batch-billing by hand.
        // amount_paid itself is still never touched by recalculateBatch() regardless.
        app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $schoolId, force: $forceFeeRecalc);
    }
}
