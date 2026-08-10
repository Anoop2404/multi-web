<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * WP-04 — manual Sahodaya-to-State nomination (master plan §27). Persists to
 * fest_state_nomination_batches/_selections (tenant connection, migration
 * 2026_08_10_000001_fest_state_nomination_tables.php).
 */
class FestStateNominationService
{
    /**
     * Build the eligible candidate pool from combined Region/Finale certified results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatePool(FestStateProgram $program, FestEvent $hubEvent): array
    {
        $candidates = [];

        $items = FestEventItem::where('event_id', $hubEvent->id)->get();

        foreach ($items as $item) {
            if (! $item->state_program_item_id) {
                continue;
            }

            $marks = FestMark::where('event_id', $hubEvent->id)
                ->where('item_id', $item->id)
                ->whereNotNull('position')
                ->with(['participant.registration', 'participant.student'])
                ->orderBy('position')
                ->get();

            foreach ($marks as $mark) {
                $participant = $mark->participant;
                if (! $participant || ! $participant->registration) {
                    continue;
                }

                $candidates[] = [
                    'mark_id'          => $mark->id,
                    'source_event_id'  => $hubEvent->id,
                    'registration_id'  => $participant->registration->id,
                    'participant_id'   => $participant->id,
                    'item_id'          => $item->state_program_item_id,
                    'item_code'        => $item->item_code,
                    'item_title'       => $item->title,
                    'school_id'        => $participant->registration->school_id,
                    'student_name'     => $participant->student?->name ?? 'Participant',
                    'class_name'       => $participant->student?->class_name ?? null,
                    'source_position'  => $mark->position,
                    'grade'            => $mark->grade,
                    'score'            => $mark->score,
                    'is_eligible'      => true,
                ];
            }
        }

        return $candidates;
    }

    /** Get or create the open nomination batch for this hub event + program. */
    public function openBatch(FestStateProgram $program, FestEvent $hubEvent): FestStateNominationBatch
    {
        return FestStateNominationBatch::firstOrCreate(
            ['state_program_id' => $program->id, 'hub_event_id' => $hubEvent->id],
            ['status' => 'candidate_pool_building']
        );
    }

    /**
     * Select one candidate as primary or reserve for its item. Enforces the item's State
     * quota (FestStateProgramItem.qualify_count) transactionally with a row lock so two
     * committee members can't both claim the last primary slot (§27.3).
     *
     * @param  array<string, mixed>  $candidate  A row from candidatePool(), or at minimum ['mark_id' => ...].
     */
    public function select(
        FestStateNominationBatch $batch,
        array $candidate,
        string $nominationType,
        int $priorityOrder = 1,
        ?User $selectedBy = null,
        ?string $skipReason = null,
    ): FestStateNominationSelection {
        abort_if($batch->isCertified(), 422, 'This nomination batch is already certified — withdraw/replace instead of re-selecting.');

        return DB::transaction(function () use ($batch, $candidate, $nominationType, $priorityOrder, $selectedBy, $skipReason) {
            // Lock the batch row so concurrent selections against the same item serialize here
            // instead of both reading "quota not yet full" and both writing.
            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();

            $itemId = $candidate['item_id'] ?? null;

            if ($nominationType === 'primary' && $itemId) {
                $quota = $this->itemQuota($itemId, $candidate['item_code'] ?? null);
                $existingPrimaries = $batch->selections()
                    ->where('item_id', $itemId)
                    ->where('nomination_type', 'primary')
                    ->where('status', 'selected')
                    ->count();

                if ($quota !== null && $existingPrimaries >= $quota) {
                    abort(422, "This item already has its full State nomination quota ({$quota}) selected.");
                }
            }

            if (! empty($candidate['mark_id'])) {
                $duplicate = $batch->selections()
                    ->where('mark_id', $candidate['mark_id'])
                    ->where('status', 'selected')
                    ->exists();
                abort_if($duplicate, 422, 'This candidate is already selected in this batch.');
            }

            $selection = $batch->selections()->create([
                'item_id'         => $itemId,
                'item_code'       => $candidate['item_code'] ?? null,
                'item_title'      => $candidate['item_title'] ?? null,
                'source_event_id' => $candidate['source_event_id'] ?? null,
                'mark_id'         => $candidate['mark_id'] ?? null,
                'registration_id' => $candidate['registration_id'] ?? null,
                'participant_id'  => $candidate['participant_id'] ?? null,
                'partition_key'   => $candidate['partition_key'] ?? null,
                'school_id'       => $candidate['school_id'] ?? null,
                'school_name'     => $candidate['school_name'] ?? null,
                'student_name'    => $candidate['student_name'] ?? null,
                'class_name'      => $candidate['class_name'] ?? null,
                'source_position' => $candidate['source_position'] ?? null,
                'grade'           => $candidate['grade'] ?? null,
                'score'           => $candidate['score'] ?? null,
                'nomination_type' => $nominationType,
                'priority_order'  => $priorityOrder,
                'skip_reason'     => $skipReason,
                'status'          => 'selected',
                'selected_by'     => $selectedBy?->id,
            ]);

            if ($batch->status === 'candidate_pool_building') {
                $batch->update(['status' => 'selection_in_progress']);
            }

            return $selection;
        });
    }

    public function unselect(FestStateNominationSelection $selection): void
    {
        abort_if($selection->batch?->isCertified(), 422, 'This nomination batch is already certified — withdrawal needs the replacement workflow instead.');

        $selection->update(['status' => 'withdrawn']);
    }

    private function itemQuota(?string $itemId, ?string $itemCode): ?int
    {
        if (! $itemId && ! $itemCode) {
            return null;
        }

        $item = FestStateProgramItem::query()
            ->when($itemId, fn ($q) => $q->where('id', $itemId))
            ->when(! $itemId && $itemCode, fn ($q) => $q->where('item_code', $itemCode))
            ->first();

        return $item?->qualify_count;
    }

    /**
     * Maker prepares a draft nomination in one call — a convenience wrapper over
     * openBatch()/select() that persists every selection durably but keeps returning the
     * same plain-array shape existing callers expect.
     *
     * @param  array<int, array{mark_id?: int, nomination_type?: string, priority_order?: int}>  $selections
     */
    public function createMakerNomination(FestStateProgram $program, FestEvent $hubEvent, User $maker, array $selections): array
    {
        $batch = $this->openBatch($program, $hubEvent);
        $batch->update(['maker_id' => $maker->id]);

        foreach ($selections as $candidate) {
            $this->select(
                $batch,
                $candidate,
                $candidate['nomination_type'] ?? 'primary',
                $candidate['priority_order'] ?? 1,
                $maker,
            );
        }

        $batch->update(['status' => 'ready_for_check']);
        $batch->refresh();

        return [
            'id'               => $batch->id,
            'state_program_id' => $batch->state_program_id,
            'hub_event_id'     => $batch->hub_event_id,
            'maker_id'         => $batch->maker_id,
            'status'           => $batch->status,
            'selections'       => $selections,
            'created_at'       => $batch->created_at?->toIso8601String(),
        ];
    }

    /**
     * Checker certifies — must be a different authorized user than the maker (§27.5).
     * Persists certification on the batch, which locks it: select()/unselect() reject any
     * further change once isCertified() is true.
     */
    public function certifyCheckerNomination(array $nominationBatch, User $checker): array
    {
        if (($nominationBatch['maker_id'] ?? null) === $checker->id) {
            throw new \InvalidArgumentException('Checker cannot be the same user as the nomination maker.');
        }

        $batch = FestStateNominationBatch::findOrFail($nominationBatch['id']);

        $batch->update([
            'checker_id'   => $checker->id,
            'certified_at' => now(),
            'status'       => 'certified',
        ]);
        $batch->refresh();

        $nominationBatch['checker_id']   = $batch->checker_id;
        $nominationBatch['certified_at'] = $batch->certified_at->toIso8601String();
        $nominationBatch['status']       = $batch->status;

        return $nominationBatch;
    }
}
