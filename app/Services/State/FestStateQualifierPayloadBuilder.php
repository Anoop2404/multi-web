<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateProgram;
use App\Models\FestStateSubmissionOutbox;
use App\Services\Events\FestPartitionService;
use Illuminate\Support\Str;

class FestStateQualifierPayloadBuilder
{
    public function __construct(
        private FestPartitionService $partitions,
    ) {}

    /** @return array<string, mixed> */
    public function build(FestStateProgram $program, FestEvent $sourceEvent, string $sourceTenantId): array
    {
        $entries = $this->entriesFromCertifiedNomination($program, $sourceEvent)
            ?? $this->entriesFromDirectMarks($program, $sourceEvent);

        return [
            'state_program_id' => $program->id,
            'source_tenant_id' => $sourceTenantId,
            'source_event_id'  => $sourceEvent->id,
            'submitted_at'     => now()->toIso8601String(),
            'entries'          => $entries,
        ];
    }

    /**
     * WP-04 (§27.4): once a Sahodaya's committee has run the maker/checker nomination
     * workflow and certified a batch for this hub event, that curated selection is the
     * source of truth — it may include manual overrides (skip a higher scorer, promote a
     * reserve) that the raw marks alone can't express. Returns null (meaning "no certified
     * batch exists yet") so callers fall back to reading marks directly, which keeps
     * today's direct-registration path working for every Sahodaya that hasn't adopted the
     * nomination workspace yet.
     *
     * @return list<array<string, mixed>>|null
     */
    private function entriesFromCertifiedNomination(FestStateProgram $program, FestEvent $sourceEvent): ?array
    {
        $batch = FestStateNominationBatch::query()
            ->where('state_program_id', $program->id)
            ->where('hub_event_id', $sourceEvent->id)
            ->where('status', 'certified')
            ->first();

        if (! $batch) {
            return null;
        }

        $entries = [];

        foreach ($batch->primarySelections()->orderBy('priority_order')->get() as $selection) {
            if (! $selection->item_id) {
                // A certified batch should never contain a primary selection without its
                // catalog item_id backfilled, but skip defensively rather than send State
                // a qualifier entry with no item to attach it to.
                continue;
            }

            $entries[] = [
                'source_registration_id' => (string) ($selection->registration_id ?? ''),
                'source_participant_id'  => (string) ($selection->participant_id ?? ''),
                'school_id'              => $selection->school_id,
                'item_id'                => $selection->item_id,
                'item_code'              => $selection->item_code,
                'item_name'              => $selection->item_title,
                'student_name'           => $selection->student_name ?? 'Participant',
                'class_name'             => $selection->class_name,
                'position'               => $selection->source_position,
                'grade'                  => $selection->grade,
                'points'                 => $selection->score ?? 0,
                'partition_key'          => $selection->partition_key,
                'qualifier_type'         => 'state_nominated',
            ];
        }

        return $entries;
    }

    /** @return list<array<string, mixed>> */
    private function entriesFromDirectMarks(FestStateProgram $program, FestEvent $sourceEvent): array
    {
        $events = $this->sourceEvents($sourceEvent);
        $entries = [];

        foreach ($events as $event) {
            $role = $this->partitions->partitionRole($event) ?? 'standard';
            $policy = $program->qualifier_policy ?? config('fest_conduct_presets.mcs_kalotsav.qualifier_policy', []);
            $positions = $this->positionsForRole($policy, $role);

            $items = FestEventItem::where('event_id', $event->id)->get();

            foreach ($items as $item) {
                if ($this->shouldSkipItem($item)) {
                    continue;
                }

                // Per-item override (e.g. English One Act Play: qualify_count=1 caps it below
                // the role's default [1,2] even though every other item on this event qualifies two).
                $itemPositions = $item->qualify_count
                    ? array_values(array_filter($positions, fn (int $p) => $p <= (int) $item->qualify_count))
                    : $positions;

                if ($itemPositions === []) {
                    continue;
                }

                $marks = FestMark::where('event_id', $event->id)
                    ->where('item_id', $item->id)
                    ->whereNotNull('position')
                    ->whereIn('position', $itemPositions)
                    ->with(['participant.registration.participants.student'])
                    ->orderBy('position')
                    ->get();

                foreach ($marks as $mark) {
                    $participant = $mark->participant;
                    $registration = $participant?->registration;
                    if (! $participant || ! $registration) {
                        continue;
                    }

                    $student = $registration->participants->first()?->student;

                    $entries[] = [
                        'source_registration_id' => (string) $registration->id,
                        'source_participant_id'  => (string) $participant->id,
                        'school_id'              => $registration->school_id,
                        // The canonical State catalog item UUID (FestStateProgramItem.id), not this
                        // Sahodaya's own tenant-local FestEventItem.id — that integer is only unique
                        // within this one tenant database and means nothing at State level. State's
                        // item_id columns store the catalog UUID; item_code is the human-readable tie.
                        'item_id'                => $item->state_program_item_id,
                        'item_code'              => $item->item_code,
                        'item_name'              => $item->title,
                        'student_name'           => $student?->name ?? $participant->display_name ?? 'Participant',
                        'class_name'             => $student?->class_name,
                        'position'               => $mark->position,
                        'grade'                  => $mark->grade,
                        'points'                 => $mark->score ?? 0,
                        'partition_key'          => $this->partitions->partitionKey($event),
                        'qualifier_type'         => match (true) {
                            $role === 'finale' => 'district_winner',
                            in_array($role, ['region', 'cluster'], true) => 'regional_winner',
                            default => 'sahodaya_winner',
                        },
                    ];
                }
            }
        }

        return $entries;
    }

    public function enqueue(FestStateProgram $program, FestEvent $sourceEvent, string $sourceTenantId, ?int $submittedBy = null): FestStateSubmissionOutbox
    {
        // Every applicable source event (the event itself, or — for a partitioned hub —
        // every region/finale child, since that's where results actually live) must have
        // published results before qualifiers can go to State. Without this, an admin
        // could submit qualifiers computed from marks that were never finalized/published,
        // and — for a hub — a region that hasn't finished yet would silently contribute
        // nothing rather than blocking the submission.
        foreach ($this->sourceEvents($sourceEvent) as $event) {
            abort_unless(
                $event->results_published,
                422,
                "Results for \"{$event->title}\" must be published before submitting qualifiers to State."
            );
        }

        $payload = $this->build($program, $sourceEvent, $sourceTenantId);

        // Hash only the actual qualifier content (never submitted_at, a generation
        // timestamp baked into the payload) — otherwise the hash, and therefore the
        // idempotency key below, differs on every call even when the underlying
        // qualifiers are byte-for-byte identical, so firstOrCreate() never finds the
        // existing row and a duplicate outbox submission is created on every re-click
        // or retry. See docs — Phase 3 audit, item 6.
        $hash = hash('sha256', json_encode($payload['entries']));
        $idempotencyKey = "qualifiers:{$program->id}:{$sourceEvent->id}:{$hash}";

        return FestStateSubmissionOutbox::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'state_program_id' => $program->id,
                'source_event_id'  => $sourceEvent->id,
                'submission_type'  => 'qualifier_batch',
                'payload'          => $payload,
                'payload_hash'     => $hash,
                'status'           => 'pending',
                'submitted_by'     => $submittedBy,
            ]
        );
    }

    /** @return list<FestEvent> */
    private function sourceEvents(FestEvent $sourceEvent): array
    {
        if ($this->partitions->isPartitionedHub($sourceEvent)) {
            return $this->partitions->partitions($sourceEvent)->all();
        }

        return [$sourceEvent];
    }

    /**
     * Manual, General Rules #15: "First and second position winners ... shall be selected ...
     * only two participants/teams from a Sahodaya are eligible to register in the State level
     * competition." Top 2, always — for a partitioned finale AND for a standard (non-partitioned)
     * Sahodaya alike. Previously this fell through to a hardcoded [1,2,3] for the standard case,
     * silently over-submitting 3rd-place finishers to State.
     *
     * @return list<int>
     */
    private function positionsForRole(array $policy, string $role): array
    {
        if ($role === 'finale') {
            return $policy['district']['positions'] ?? [1, 2];
        }

        if (in_array($role, ['region', 'cluster'], true)) {
            return $policy['regional']['positions'] ?? [1];
        }

        return $policy['standard']['positions'] ?? [1, 2];
    }

    private function shouldSkipItem(FestEventItem $item): bool
    {
        // A Sahodaya/school's own custom item (owner_level != 'state', no state_program_item_id
        // back-reference) has no meaning at State level and must never qualify there by accident —
        // it only ends up in this event's item list because it's conducted alongside the real
        // state-catalog items, not because it's part of the state program.
        if (! $item->state_program_item_id) {
            return true;
        }

        $criteria = $item->criteria_json ?? [];

        return ($criteria['mcs_only'] ?? false) === true
            || ($criteria['state_eligible'] ?? true) === false;
    }
}
