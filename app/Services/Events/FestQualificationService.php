<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestQualification;
use App\Models\FestQualificationLotDraw;
use App\Models\FestRegistration;
use Illuminate\Support\Str;

class FestQualificationService
{
  /** @return array{promoted: int, skipped: int} */
  public function promoteWinners(FestEvent $fromEvent, FestEvent $toEvent): array
  {
    abort_if($fromEvent->tenant_id !== $toEvent->tenant_id, 422, 'Events must belong to the same Sahodaya.');

    $promoted = 0;
    $skipped = 0;
    $handledRegistrations = [];

    $items = FestEventItem::where('event_id', $fromEvent->id)->get();

    foreach ($items as $item) {
      $limit = max(1, (int) ($item->qualify_count ?? 3));
      $mode = $item->tiebreak_mode ?: 'none';

      $allMarks = FestMark::where('event_id', $fromEvent->id)
        ->where('item_id', $item->id)
        ->whereNotNull('position')
        ->with(['participant.registration.participants', 'participant.registration.groups'])
        ->orderBy('position')
        ->orderBy('id')
        ->get();

      $marks = $this->selectMarksForPromotion($allMarks, $limit, $mode, $fromEvent, $item, $toEvent);

      $targetItem = $this->matchingItem($toEvent, $item);

      foreach ($marks as $mark) {
        $participant = $mark->participant;
        if (! $participant) {
          $skipped++;

          continue;
        }

        $participant->loadMissing('registration');
        $sourceReg = $participant->registration;

        if ($sourceReg && isset($handledRegistrations[$sourceReg->id])) {
          continue;
        }

        $qual = FestQualification::firstOrCreate(
          [
            'event_id'       => $fromEvent->id,
            'item_id'        => $item->id,
            'participant_id' => $participant->id,
          ],
          [
            'next_level_event_id' => $toEvent->id,
            'promoted_at'         => now(),
          ]
        );

        if (! $qual->wasRecentlyCreated) {
          $skipped++;

          continue;
        }

        if ($targetItem && $sourceReg) {
          $sourceReg->loadMissing('groups', 'participants');
          $this->ensurePromotedRegistration($toEvent, $targetItem, $sourceReg);
          $handledRegistrations[$sourceReg->id] = true;
        }

        $promoted++;
      }
    }

    return compact('promoted', 'skipped');
  }

  /**
   * Apply tie-break policy when selecting who promotes (FRD-08 Phase 5).
   *
   * @param  \Illuminate\Support\Collection<int, FestMark>  $allMarks
   * @return \Illuminate\Support\Collection<int, FestMark>
   */
  private function selectMarksForPromotion($allMarks, int $limit, string $mode, FestEvent $fromEvent, FestEventItem $item, FestEvent $toEvent)
  {
    if ($mode === 'none' || $mode === '' || $mode === null) {
      return $allMarks->where('position', '<=', $limit)->values();
    }

    if ($mode === 'include_all_ties') {
      // Anyone at or better than the Nth unique rank position.
      $cutoffRank = $allMarks->pluck('position')->unique()->sort()->values()->take($limit)->last();

      return $cutoffRank === null
        ? collect()
        : $allMarks->where('position', '<=', $cutoffRank)->values();
    }

    if ($mode === 'exclude_ties') {
      $selected = collect();
      foreach ($allMarks->groupBy('position')->sortKeys() as $position => $group) {
        if ($selected->count() >= $limit) {
          break;
        }
        if ($group->count() > 1 && $selected->count() + $group->count() > $limit) {
          // Skip contested rank that would overflow quota.
          continue;
        }
        if ($selected->count() + $group->count() <= $limit) {
          $selected = $selected->concat($group);
        }
      }

      return $selected->values();
    }

    if ($mode === 'lot_draw') {
      $definite = $allMarks->where('position', '<', $limit)->values();
      $slotsLeft = max(0, $limit - $definite->count());
      $contested = $allMarks->where('position', $limit)->values();

      if ($slotsLeft <= 0 || $contested->isEmpty()) {
        return $definite->take($limit)->values();
      }

      if ($contested->count() <= $slotsLeft) {
        return $definite->concat($contested)->values();
      }

      $seed = (string) Str::uuid();
      $shuffled = $contested->shuffle();
      $picked = $shuffled->take($slotsLeft)->values();

      FestQualificationLotDraw::create([
        'event_id' => $toEvent->id,
        'item_id' => $item->id,
        'from_event_id' => $fromEvent->id,
        'cutoff_position' => $limit,
        'contested_participant_ids' => $contested->pluck('participant_id')->all(),
        'selected_participant_ids' => $picked->pluck('participant_id')->all(),
        'method' => 'auto_random',
        'seed' => $seed,
        'drawn_by' => auth()->id(),
        'drawn_at' => now(),
        'notes' => "Auto lot-draw for {$slotsLeft} of {$contested->count()} tied at position {$limit}.",
      ]);

      return $definite->concat($picked)->values();
    }

    if ($mode === 'manual') {
      abort(422, "Item \"{$item->title}\" requires a manual tie-break before promotion (tiebreak_mode=manual).");
    }

    // secondary_score / unknown → fall back to legacy position cutoff
    return $allMarks->where('position', '<=', $limit)->values();
  }

  /** @return array{promoted: int, skipped: int, rounds_processed: int, rounds_skipped: int} */
  public function promoteAllSchoolRounds(FestEvent $parent): array
  {
    abort_unless(
      in_array($parent->level_round ?? 'sahodaya', ['sahodaya', 'state'], true),
      422,
      'Bulk promotion runs on a cluster-level parent event.'
    );

    $schoolRounds = FestEvent::query()
      ->where('parent_event_id', $parent->id)
      ->where('level_round', 'school')
      ->get();

    $promoted = 0;
    $skipped = 0;
    $roundsProcessed = 0;
    $roundsSkipped = 0;

    foreach ($schoolRounds as $round) {
      if (! $round->results_published) {
        $roundsSkipped++;

        continue;
      }

      $result = $this->promoteWinners($round, $parent);
      $promoted += $result['promoted'];
      $skipped += $result['skipped'];
      $roundsProcessed++;
    }

    return compact('promoted', 'skipped', 'roundsProcessed', 'roundsSkipped');
  }

  public function resolveNextLevelEvent(FestEvent $from): ?FestEvent
  {
    $level = $from->level_round ?? 'sahodaya';

    if ($level === 'school') {
      if ($from->parent_event_id) {
        return FestEvent::find($from->parent_event_id);
      }

      return FestEvent::query()
        ->where('tenant_id', $from->tenant_id)
        ->where('event_type', $from->event_type)
        ->where('level_round', 'sahodaya')
        ->when($from->state_program_id, fn ($q) => $q->where('state_program_id', $from->state_program_id))
        ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
        ->orderByDesc('event_start')
        ->first();
    }

    if ($level === 'sahodaya' && $from->state_program_id) {
      if ($from->event_type === 'sports') {
        return null;
      }

      return FestEvent::query()
        ->where('tenant_id', $from->tenant_id)
        ->where('state_program_id', $from->state_program_id)
        ->where('level_round', 'state')
        ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
        ->orderByDesc('event_start')
        ->first();
    }

    return null;
  }

  /** @return list<FestEvent> */
  public function candidateNextEvents(FestEvent $from): array
  {
    $suggested = $this->resolveNextLevelEvent($from);
    $candidates = FestEvent::query()
      ->where('tenant_id', $from->tenant_id)
      ->where('id', '!=', $from->id)
      ->where('event_type', $from->event_type)
      ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
      ->orderByDesc('event_start')
      ->get(['id', 'title', 'status', 'level_round']);

  if ($suggested) {
      $candidates = $candidates->sortByDesc(fn (FestEvent $e) => $e->id === $suggested->id ? 1 : 0)->values();
    }

    return $candidates->all();
  }

  public function revokeQualification(FestQualification $qual): void
  {
    $qual->load(['participant.registration', 'item', 'nextLevelEvent']);

    $registration = $this->findPromotedRegistration($qual);
    if ($registration) {
      app(FestRegistrationService::class)->cancel($registration, $registration->event, notify: false);
    }

    FestQualification::where('event_id', $qual->event_id)
      ->where('item_id', $qual->item_id)
      ->where('next_level_event_id', $qual->next_level_event_id)
      ->whereHas('participant', fn ($q) => $q->where('registration_id', $qual->participant?->registration_id))
      ->delete();

    if (FestQualification::where('id', $qual->id)->exists()) {
      $qual->delete();
    }
  }

  private function ensurePromotedRegistration(FestEvent $event, FestEventItem $item, FestRegistration $source): void
  {
    $existing = $this->findExistingPromotedRegistration($event, $item, $source);
    if ($existing) {
      return;
    }

    $isGroup = in_array($item->participant_type, ['group', 'team'], true)
      || $source->groups()->exists()
      || $source->participants()->where('participant_role', '!=', 'standby')->count() > 1;

    if ($isGroup) {
      $this->createGroupRegistration($event, $item, $source);

      return;
    }

    $performer = $source->participants->first(fn (FestParticipant $p) => $p->participant_role !== 'standby');
    if (! $performer) {
      return;
    }

    if ($performer->student_id) {
      $this->createIndividualRegistration($event, $item, $source, $performer);

      return;
    }

    if ($performer->teacher_id) {
      $this->createTeacherRegistration($event, $item, $source, $performer);
    }
  }

    private function createIndividualRegistration(
        FestEvent $event,
        FestEventItem $item,
        FestRegistration $source,
        FestParticipant $performer,
    ): void {
        $registration = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $source->school_id,
            'mode'         => 'winner_only',
            'status'       => 'approved',
            'submitted_at' => now(),
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'student_id'       => $performer->student_id,
            'participant_type' => 'student',
            'participant_role' => 'performer',
        ]);

        app(FestRegistrationApprovalService::class)->approve($registration->fresh(['participants', 'item', 'event']));
    }

    private function createTeacherRegistration(
        FestEvent $event,
        FestEventItem $item,
        FestRegistration $source,
        FestParticipant $performer,
    ): void {
        $registration = FestRegistration::create([
            'event_id'     => $event->id,
            'item_id'      => $item->id,
            'school_id'    => $source->school_id,
            'mode'         => 'winner_only',
            'status'       => 'approved',
            'submitted_at' => now(),
        ]);

        FestParticipant::create([
            'registration_id'  => $registration->id,
            'teacher_id'       => $performer->teacher_id,
            'participant_type' => 'teacher',
            'participant_role' => 'performer',
        ]);

        app(FestRegistrationApprovalService::class)->approve($registration->fresh(['participants', 'item', 'event']));
    }

  private function createGroupRegistration(FestEvent $event, FestEventItem $item, FestRegistration $source): void
  {
    $registration = FestRegistration::create([
      'event_id'     => $event->id,
      'item_id'      => $item->id,
      'school_id'    => $source->school_id,
      'mode'         => 'winner_only',
      'status'       => 'approved',
      'submitted_at' => now(),
    ]);

    $sourceGroup = $source->groups->first();
    $groupId = null;
    if ($sourceGroup) {
      $group = FestGroup::create([
        'registration_id' => $registration->id,
        'team_name'       => $sourceGroup->team_name,
        'coach_name'      => $sourceGroup->coach_name,
        'coach_phone'     => $sourceGroup->coach_phone,
        'manager_name'    => $sourceGroup->manager_name,
        'manager_phone'   => $sourceGroup->manager_phone,
      ]);
      $groupId = $group->id;
    }

        foreach ($source->participants as $participant) {
            FestParticipant::create([
                'registration_id'  => $registration->id,
                'group_id'         => $groupId,
                'student_id'       => $participant->student_id,
                'teacher_id'       => $participant->teacher_id,
                'participant_type' => $participant->participant_type,
                'participant_role' => $participant->participant_role ?? 'performer',
            ]);
        }

        app(FestRegistrationApprovalService::class)->approve($registration->fresh(['participants', 'item', 'event']));
    }

  private function findExistingPromotedRegistration(
    FestEvent $event,
    FestEventItem $item,
    FestRegistration $source,
  ): ?FestRegistration {
    return FestRegistration::where('event_id', $event->id)
      ->where('item_id', $item->id)
      ->where('school_id', $source->school_id)
      ->where('mode', 'winner_only')
      ->where('status', '!=', 'withdrawn')
      ->whereHas('participants', function ($q) use ($source) {
        $studentIds = $source->participants->pluck('student_id')->filter()->values();
        if ($studentIds->isNotEmpty()) {
          $q->whereIn('student_id', $studentIds);
        }
      })
      ->first();
  }

  private function findPromotedRegistration(FestQualification $qual): ?FestRegistration
  {
    $toEvent = $qual->nextLevelEvent;
    $participant = $qual->participant;
    if (! $toEvent || ! $participant?->registration) {
      return null;
    }

    $targetItem = $this->matchingItem($toEvent, $qual->item);
    if (! $targetItem) {
      return null;
    }

    return $this->findExistingPromotedRegistration($toEvent, $targetItem, $participant->registration);
  }

  private function matchingItem(FestEvent $toEvent, FestEventItem $fromItem): ?FestEventItem
  {
    if ($fromItem->item_code) {
      $byCode = FestEventItem::where('event_id', $toEvent->id)
        ->where('item_code', $fromItem->item_code)
        ->first();

      if ($byCode) {
        return $byCode;
      }
    }

        return FestEventItem::where('event_id', $toEvent->id)
        ->where('title', $fromItem->title)
        ->first()
      ?? FestEventItem::where('event_id', $toEvent->id)
        ->where('category', $fromItem->category)
        ->where('participant_type', $fromItem->participant_type)
        ->first();
  }

  /** @return list<array<string, mixed>> */
  public function schoolSportsWinnerCandidates(\App\Models\Tenant $school): array
  {
    $schoolEvents = FestEvent::query()
      ->where('conducting_school_id', $school->id)
      ->where('event_type', 'sports')
      ->where('level_round', 'school')
      ->whereNotNull('parent_event_id')
      ->with(['parentEvent', 'items'])
      ->orderByDesc('event_start')
      ->get();

    $blocks = [];

    foreach ($schoolEvents as $schoolEvent) {
      $target = $schoolEvent->parentEvent;
      if (! $target || ! in_array($target->status, ['published', 'registration_open', 'ongoing'], true)) {
        continue;
      }

      $items = [];
      foreach ($schoolEvent->items as $item) {
        $targetItem = $this->matchingItem($target, $item);
        if (! $targetItem) {
          continue;
        }

        $limit = $item->qualify_count ?? 3;
        $marks = FestMark::where('event_id', $schoolEvent->id)
          ->where('item_id', $item->id)
          ->whereNotNull('position')
          ->where('position', '<=', $limit)
          ->with(['participant.student', 'participant.teacher', 'participant.registration'])
          ->orderBy('position')
          ->get();

        $winners = [];
        foreach ($marks as $mark) {
          $participant = $mark->participant;
          $sourceReg = $participant?->registration;
          $already = $sourceReg && $this->findExistingPromotedRegistration($target, $targetItem, $sourceReg);

          $winners[] = [
            'mark_id'           => $mark->id,
            'position'          => $mark->position,
            'participant_name'  => $participant?->student?->name ?? $participant?->teacher?->name ?? 'Participant',
            'already_submitted' => (bool) $already,
          ];
        }

        if ($winners !== []) {
          $items[] = [
            'item_id'    => $item->id,
            'item_title' => $item->title,
            'winners'    => $winners,
          ];
        }
      }

      if ($items !== []) {
        $blocks[] = [
          'school_event' => $schoolEvent->only('id', 'title', 'status'),
          'target_event' => $target->only('id', 'title', 'status'),
          'items'        => $items,
        ];
      }
    }

    return $blocks;
  }

  /** @return array{submitted: int, skipped: int} */
  public function submitSchoolSportsWinners(\App\Models\Tenant $school, FestEvent $schoolEvent, array $markIds): array
  {
    abort_if($schoolEvent->conducting_school_id !== $school->id, 403);
    abort_if($schoolEvent->event_type !== 'sports' || $schoolEvent->level_round !== 'school', 422);
    abort_unless($schoolEvent->parent_event_id, 422, 'Link this school event to a Sahodaya parent event first.');

    $target = FestEvent::findOrFail($schoolEvent->parent_event_id);
    abort_if($target->tenant_id !== $school->parent_id, 403);

    $submitted = 0;
    $skipped = 0;

    $marks = FestMark::where('event_id', $schoolEvent->id)
      ->whereIn('id', $markIds)
      ->with(['participant.registration.participants', 'participant.registration.groups', 'item'])
      ->get();

    foreach ($marks as $mark) {
      $sourceReg = $mark->participant?->registration;
      $targetItem = $this->matchingItem($target, $mark->item);

      if (! $sourceReg || ! $targetItem) {
        $skipped++;

        continue;
      }

      if ($this->findExistingPromotedRegistration($target, $targetItem, $sourceReg)) {
        $skipped++;

        continue;
      }

      $sourceReg->loadMissing('groups', 'participants');
      $this->ensurePromotedRegistration($target, $targetItem, $sourceReg);

      FestQualification::firstOrCreate(
        [
          'event_id'       => $schoolEvent->id,
          'item_id'        => $mark->item_id,
          'participant_id' => $mark->participant_id,
        ],
        [
          'next_level_event_id' => $target->id,
          'promoted_at'         => now(),
        ],
      );

      $submitted++;
    }

    return compact('submitted', 'skipped');
  }
}
