<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestPhaseAdvancement;
use App\Models\FestRegistration;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Manual, same-event, phase-to-phase advancement: after a regional phase's per-region
 * results are published, a Sahodaya admin picks a subset of participants/teams across
 * those regions and registers them directly into a later (non-regional) phase's item for
 * the same event — e.g. Off Stage/Sargadhara region winners advancing into District
 * Kalotsav.
 *
 * Deliberately independent of FestQualificationService::promoteWinners() and the
 * FestQualification table, which drive the separate Sahodaya-to-State qualification
 * cascade (auto top-N selection, revoke-on-reject). This is an explicit admin pick with
 * its own audit trail (fest_phase_advancements) and never touches that cascade.
 */
class FestPhaseAdvancementService
{
    /**
     * Ranked pool of registrations across every published region leaf of $fromItem's
     * phase, for the admin to choose a subset from.
     *
     * @return list<array<string, mixed>>
     */
    public function eligibleCandidates(FestEventItem $fromItem): array
    {
        $phase = $fromItem->phase;
        abort_if(! $phase || ! $phase->isRegional(), 422, 'This item is not in a regional phase.');

        $root = $fromItem->event->rootEvent();
        $leaves = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $phase->id)
            ->whereNotNull('region_id')
            ->where('results_published', true)
            ->with('region')
            ->get();

        $rows = [];
        foreach ($leaves as $leaf) {
            $leafItem = FestEventItem::where('event_id', $leaf->id)
                ->where('inherited_from_item_id', $fromItem->id)
                ->first();
            if (! $leafItem) {
                continue;
            }

            $registrations = FestRegistration::where('event_id', $leaf->id)
                ->where('item_id', $leafItem->id)
                ->where('status', 'approved')
                ->with(['participants.student:id,name,reg_no', 'participants.teacher:id,name,reg_no', 'groups', 'school:id,name'])
                ->get();

            foreach ($registrations as $registration) {
                $performerIds = $registration->participants
                    ->where('participant_role', '!=', 'standby')
                    ->pluck('id');
                $marks = FestMark::where('event_id', $leaf->id)
                    ->where('item_id', $leafItem->id)
                    ->whereIn('participant_id', $performerIds)
                    ->get();

                $rows[] = [
                    'registration_id' => $registration->id,
                    'region_id' => $leaf->region_id,
                    'region_name' => $leaf->region?->name,
                    'school_id' => $registration->school_id,
                    'school_name' => $registration->school?->name,
                    'team_name' => $registration->groups->first()?->team_name,
                    'participants' => $registration->participants->map(fn (FestParticipant $p) => [
                        'name' => $p->student?->name ?? $p->teacher?->name,
                        'reg_no' => $p->student?->reg_no ?? $p->teacher?->reg_no,
                        'role' => $p->participant_role,
                    ])->values()->all(),
                    'position' => $marks->min('position'),
                    'grade' => $marks->first()?->grade,
                    'already_advanced' => FestPhaseAdvancement::where('from_registration_id', $registration->id)
                        ->whereNull('withdrawn_at')
                        ->exists(),
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX));

        return $rows;
    }

    /**
     * Advance the given source registrations into $toItem. Idempotent per (source
     * registration, target phase): re-advancing an already-live advancement returns the
     * existing record instead of creating a duplicate.
     *
     * @param  list<int>  $registrationIds
     * @return Collection<int, FestPhaseAdvancement>
     */
    public function advance(FestEventItem $fromItem, FestEventItem $toItem, array $registrationIds, ?int $actorId = null): Collection
    {
        $fromPhase = $fromItem->phase;
        abort_if(! $fromPhase || ! $fromPhase->isRegional(), 422, 'The source item is not in a regional phase.');

        $toPhase = $toItem->phase;
        abort_if(! $toPhase, 422, 'The target item has no competition phase.');
        abort_if($toPhase->id === $fromPhase->id, 422, 'Target item must belong to a different phase.');

        $root = $fromItem->event->rootEvent();
        abort_unless($toItem->event->rootEvent()->id === $root->id, 422, 'Target item must belong to the same event.');

        $targetLeaf = FestEvent::where('parent_event_id', $root->id)
            ->where('source_phase_id', $toPhase->id)
            ->whereNull('region_id')
            ->first();
        abort_unless($targetLeaf, 422, 'The target phase has no non-regional operational event configured.');

        $targetLeafItem = $toItem->event_id === $targetLeaf->id
            ? $toItem
            : FestEventItem::where('event_id', $targetLeaf->id)
                ->where('inherited_from_item_id', $toItem->id)
                ->first();
        abort_unless($targetLeafItem, 422, 'The target item is not configured on its operational event.');

        $sources = FestRegistration::whereIn('id', $registrationIds)
            ->with(['participants', 'groups', 'school'])
            ->get();
        abort_if($sources->isEmpty(), 422, 'Select at least one participant or team to advance.');

        $results = collect();

        foreach ($sources as $source) {
            $existing = FestPhaseAdvancement::where('from_registration_id', $source->id)
                ->where('to_phase_id', $toPhase->id)
                ->whereNull('withdrawn_at')
                ->first();

            if ($existing) {
                $results->push($existing);

                continue;
            }

            $targetRegistration = $this->createTargetRegistration($targetLeaf, $targetLeafItem, $source);

            $results->push(FestPhaseAdvancement::create([
                'root_event_id' => $root->id,
                'from_phase_id' => $fromPhase->id,
                'to_phase_id' => $toPhase->id,
                'from_item_id' => $fromItem->id,
                'to_item_id' => $toItem->id,
                'from_registration_id' => $source->id,
                'target_registration_id' => $targetRegistration->id,
                'region_id' => $source->event?->region_id,
                'advanced_by' => $actorId,
                'advanced_at' => now(),
            ]));
        }

        return $results;
    }

    public function withdraw(FestPhaseAdvancement $advancement, ?int $actorId = null): void
    {
        abort_if($advancement->isWithdrawn(), 422, 'This advancement was already withdrawn.');

        if ($advancement->target_registration_id) {
            FestRegistration::whereKey($advancement->target_registration_id)->update([
                'status' => 'withdrawn',
            ]);
        }

        $advancement->update([
            'withdrawn_at' => now(),
            'withdrawn_by' => $actorId,
        ]);
    }

    private function createTargetRegistration(FestEvent $targetLeaf, FestEventItem $targetItem, FestRegistration $source): FestRegistration
    {
        $isGroup = in_array($targetItem->participant_type, ['group', 'team'], true) || $source->groups->isNotEmpty();

        $existing = FestRegistration::where('event_id', $targetLeaf->id)
            ->where('item_id', $targetItem->id)
            ->where('school_id', $source->school_id)
            ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
            ->first();
        abort_if($existing, 422, "{$source->school?->name} already has an active registration for this item — cancel it before advancing this entry.");

        $registration = FestRegistration::create([
            'event_id' => $targetLeaf->id,
            'item_id' => $targetItem->id,
            'school_id' => $source->school_id,
            'mode' => 'phase_advance',
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        if (! $isGroup) {
            $performer = $source->participants->first(fn (FestParticipant $p) => $p->participant_role !== 'standby');
            if ($performer) {
                FestParticipant::create([
                    'registration_id' => $registration->id,
                    'student_id' => $performer->student_id,
                    'teacher_id' => $performer->teacher_id,
                    'participant_type' => $performer->student_id ? 'student' : 'teacher',
                    'participant_role' => 'performer',
                ]);
            }

            return $registration;
        }

        $sourceGroup = $source->groups->first();
        $groupId = null;
        if ($sourceGroup) {
            $groupId = FestGroup::create([
                'registration_id' => $registration->id,
                'team_name' => $sourceGroup->team_name,
                'coach_name' => $sourceGroup->coach_name,
                'coach_phone' => $sourceGroup->coach_phone,
                'manager_name' => $sourceGroup->manager_name,
                'manager_phone' => $sourceGroup->manager_phone,
            ])->id;
        }

        foreach ($source->participants as $participant) {
            FestParticipant::create([
                'registration_id' => $registration->id,
                'group_id' => $groupId,
                'student_id' => $participant->student_id,
                'teacher_id' => $participant->teacher_id,
                'participant_type' => $participant->participant_type,
                'participant_role' => $participant->participant_role ?? 'performer',
            ]);
        }

        return $registration;
    }
}
