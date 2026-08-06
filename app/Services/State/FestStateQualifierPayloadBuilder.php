<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
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
                        'item_id'                => $item->id,
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

        return [
            'state_program_id' => $program->id,
            'source_tenant_id' => $sourceTenantId,
            'source_event_id'  => $sourceEvent->id,
            'submitted_at'     => now()->toIso8601String(),
            'entries'          => $entries,
        ];
    }

    public function enqueue(FestStateProgram $program, FestEvent $sourceEvent, string $sourceTenantId, ?int $submittedBy = null): FestStateSubmissionOutbox
    {
        $payload = $this->build($program, $sourceEvent, $sourceTenantId);
        $hash = hash('sha256', json_encode($payload));
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
        $criteria = $item->criteria_json ?? [];

        return ($criteria['mcs_only'] ?? false) === true
            || ($criteria['state_eligible'] ?? true) === false;
    }
}
