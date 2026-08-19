<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\FestRegistration;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\FestEventItem;
use App\Models\FestAttendance;
use App\Models\FestMark;
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
    ): FestSchoolPhaseRegionSelection {
        $root = $event->rootEvent();
        abort_unless($root->workflow_mode === FestPhasedWorkflowService::MODE, 422, 'This event does not use phase-specific region selection.');
        abort_unless($phase->event_id === $root->id && $phase->isRegional(), 422, 'Select a region only for a regional source phase.');

        $allowed = FestPhaseRegion::where('phase_id', $phase->id)
            ->where('region_id', $regionId)
            ->where('enabled', true)
            ->first();
        abort_if(! $allowed, 422, 'That region is not enabled for this phase.');

        return DB::transaction(function () use ($root, $phase, $schoolId, $regionId, $actorId, $override, $reason, $allowed) {
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
            ]);

            $selection->fill([
                'region_id' => $regionId,
                'changed_at' => $changed ? now() : $selection->changed_at,
                'changed_by' => $changed ? $actorId : $selection->changed_by,
                'change_reason' => $changed ? $reason : $selection->change_reason,
            ]);
            $selection->save();

            if ($changed && $override) {
                $this->migrateRegistrations($root, $phase, $schoolId, (int) $oldRegionId, $regionId);
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
            $targetItem = FestEventItem::where('event_id', $newLeaf->id)
                ->where('inherited_from_item_id', $rootItemId)
                ->first();
            abort_if(! $targetItem, 422, 'The selected region is missing a registered item. Synchronize topology and try again.');

            $registration->update(['event_id' => $newLeaf->id, 'item_id' => $targetItem->id]);
            app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));
        }

        app(FestRegistrationBatchFeeService::class)->recalculateAll($root, $schoolId);
    }
}
