<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The Sahodaya's item-by-item winner sheet for State registration.
 *
 * Organised by State item rather than by candidate, because that is the question actually being
 * answered: "this State item takes two from us — who are they?" The existing nomination workspace
 * lists a flat candidate pool, which is the same data read the other way round and leaves the
 * committee counting quota in their heads.
 *
 * Writes to the same nomination batch and selections the certified-batch submission path already
 * reads, so picks made here are pre-saved the moment they are made and registering with State is the
 * existing certify-and-push, not a second route into the State's inbox.
 *
 * Three things this adds over a flat pool:
 *
 * - **Ties are surfaced as a choice.** Two winners on the same position is the normal case at
 *   Sahodaya level, and the State takes a fixed number. A tie the committee has not resolved is
 *   reported, not silently broken by whichever row sorted first.
 * - **A refusal is recorded against the person who refused.** "Our first place cannot travel" is the
 *   commonest reason a second place goes instead, and a State asked about it later needs the record
 *   to say so. A skip note on the replacement cannot express that.
 * - **Quota is shown per item**, so "two slots, one filled" is on screen rather than enforced only at
 *   the moment of a refused save.
 */
class FestStateWinnerSheetService
{
    /** A selection row that records a winner who will not travel. */
    public const DECLINED = 'declined';

    public function __construct(private FestStateNominationService $nominations) {}

    /**
     * Every State item this Sahodaya can send winners for, with its sheet.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sheet(FestStateProgram $program, FestEvent $hubEvent, array $filters = []): Collection
    {
        $batch = $this->nominations->openBatch($program, $hubEvent);
        $candidates = collect($this->nominations->candidatePool($program, $hubEvent));
        $selections = $batch->selections()->get();

        $items = FestStateProgramItem::where('state_program_id', $program->id)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['class_group'] ?? null, fn ($q, $v) => $q->where('class_group', $v))
            ->orderBy('item_code')->get();

        $byItem = $candidates->groupBy('item_id');
        $selectionsByItem = $selections->groupBy('item_id');

        return $items
            ->map(fn (FestStateProgramItem $item) => $this->itemSheet(
                $item,
                $byItem->get($item->id, collect()),
                $selectionsByItem->get($item->id, collect()),
            ))
            // An item this Sahodaya has no results for is not a decision to make. Kept out rather than
            // listed empty, so the page is the work remaining.
            ->filter(fn (array $row) => $row['candidates'] !== [] || $row['chosen'] !== [])
            ->when($filters['only_incomplete'] ?? false, fn (Collection $rows) => $rows->where('is_complete', false))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  Collection<int, FestStateNominationSelection>  $selections
     * @return array<string, mixed>
     */
    private function itemSheet(FestStateProgramItem $item, Collection $candidates, Collection $selections): array
    {
        $quota = (int) ($item->qualify_count ?: 0);

        $chosen = $selections->where('status', 'selected')->where('nomination_type', 'primary');
        $reserves = $selections->where('status', 'selected')->where('nomination_type', 'reserve');
        $declined = $selections->where('status', self::DECLINED);

        $chosenMarkIds = $selections->where('status', 'selected')->pluck('mark_id')->filter()->all();
        $declinedMarkIds = $declined->pluck('mark_id')->filter()->all();

        $rows = $candidates
            ->sortBy(fn (array $c) => [$c['source_position'] ?? 99, -(float) ($c['score'] ?? 0)])
            ->values()
            ->map(fn (array $c) => $c + [
                'is_chosen' => in_array($c['mark_id'] ?? null, $chosenMarkIds, true),
                'is_declined' => in_array($c['mark_id'] ?? null, $declinedMarkIds, true),
            ]);

        // Positions held by more than one candidate. The committee must pick; nothing here picks for
        // them, because a Sahodaya breaking its own tie is a decision with a reason behind it.
        $tiedPositions = $rows->groupBy('source_position')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->keys()->map(fn ($p) => (int) $p)->values();

        $unresolvedTies = $tiedPositions
            ->filter(function (int $position) use ($rows, $quota) {
                $group = $rows->where('source_position', $position);

                // A tie only needs resolving when the slots cannot hold everyone tied.
                $slotsLeft = $quota - $rows->where('is_chosen', true)
                    ->where('source_position', '<', $position)->count();

                return $group->where('is_chosen', true)->isEmpty() && $group->count() > max(0, $slotsLeft);
            })->values();

        return [
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'title' => $item->title,
            'category' => $item->category,
            'class_group' => $item->class_group,
            'participant_type' => $item->participant_type,
            'quota' => $quota,
            'chosen_count' => $chosen->count(),
            'slots_left' => max(0, $quota - $chosen->count()),
            'is_complete' => $quota > 0 && $chosen->count() >= $quota,
            'is_over_quota' => $quota > 0 && $chosen->count() > $quota,
            'tied_positions' => $tiedPositions->all(),
            'unresolved_ties' => $unresolvedTies->all(),
            'candidates' => $rows->all(),
            'chosen' => $chosen->map(fn ($s) => $this->selectionRow($s))->values()->all(),
            'reserves' => $reserves->map(fn ($s) => $this->selectionRow($s))->values()->all(),
            'declined' => $declined->map(fn ($s) => $this->selectionRow($s))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function selectionRow(FestStateNominationSelection $selection): array
    {
        return [
            'id' => $selection->id,
            'mark_id' => $selection->mark_id,
            'student_name' => $selection->student_name,
            'school_name' => $selection->school_name,
            'class_name' => $selection->class_name,
            'source_position' => $selection->source_position,
            'grade' => $selection->grade,
            'score' => $selection->score,
            'nomination_type' => $selection->nomination_type,
            'priority_order' => $selection->priority_order,
            'note' => $selection->skip_reason,
        ];
    }

    /**
     * Record that a winner will not go to State.
     *
     * Stored as its own selection row rather than as a note on whoever replaces them, so the sheet can
     * say "first place declined — reason — second place goes instead". It does not consume a slot, and
     * a declined candidate cannot then be chosen without withdrawing the decline.
     */
    public function decline(FestStateNominationBatch $batch, array $candidate, string $reason, ?User $by = null): FestStateNominationSelection
    {
        abort_if($batch->isCertified(), 422, 'This batch is already registered with State. Withdraw it before changing who goes.');

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Say why this winner is not going. The State will ask, and a blank reason cannot answer it.',
            ]);
        }

        $markId = $candidate['mark_id'] ?? null;

        if ($markId) {
            $existing = $batch->selections()->where('mark_id', $markId)->first();

            if ($existing && $existing->status === 'selected') {
                throw ValidationException::withMessages([
                    'reason' => 'This candidate is currently chosen to go. Remove them from the selection first.',
                ]);
            }

            if ($existing && $existing->status === self::DECLINED) {
                $existing->update(['skip_reason' => $reason, 'selected_by' => $by?->id]);

                return $existing;
            }
        }

        return $batch->selections()->create([
            'item_id' => $candidate['item_id'] ?? null,
            'item_code' => $candidate['item_code'] ?? null,
            'item_title' => $candidate['item_title'] ?? null,
            'source_event_id' => $candidate['source_event_id'] ?? null,
            'mark_id' => $markId,
            'registration_id' => $candidate['registration_id'] ?? null,
            'participant_id' => $candidate['participant_id'] ?? null,
            'partition_key' => $candidate['partition_key'] ?? null,
            'school_id' => $candidate['school_id'] ?? null,
            'school_name' => $candidate['school_name'] ?? null,
            'student_name' => $candidate['student_name'] ?? null,
            'roll_number' => $candidate['roll_number'] ?? null,
            'class_name' => $candidate['class_name'] ?? null,
            'source_position' => $candidate['source_position'] ?? null,
            'grade' => $candidate['grade'] ?? null,
            'score' => $candidate['score'] ?? null,
            'nomination_type' => 'primary',
            'priority_order' => 0,
            'skip_reason' => $reason,
            'status' => self::DECLINED,
            'selected_by' => $by?->id,
        ]);
    }

    public function withdrawDecline(FestStateNominationBatch $batch, FestStateNominationSelection $selection): void
    {
        abort_if($batch->isCertified(), 422, 'This batch is already registered with State.');
        abort_unless($selection->batch_id === $batch->id, 404);
        abort_unless($selection->status === self::DECLINED, 422, 'That row is not a decline.');

        $selection->delete();
    }

    /**
     * Whether the sheet can be registered with State, and what is in the way.
     *
     * Short items are listed but do not block: a Sahodaya with nobody in an item sends nobody, and
     * refusing the whole submission over that would strand every other item. Unresolved ties and
     * over-quota items do block, because both mean the sheet does not yet say who is going.
     *
     * @return array{can_register: bool, blocking: list<string>, warnings: list<string>, chosen: int}
     */
    public function readiness(FestStateProgram $program, FestEvent $hubEvent): array
    {
        $sheet = $this->sheet($program, $hubEvent);
        $blocking = [];
        $warnings = [];

        foreach ($sheet as $row) {
            if ($row['unresolved_ties'] !== []) {
                $positions = implode(', ', $row['unresolved_ties']);
                $blocking[] = "{$row['item_code']} {$row['title']}: position {$positions} is tied and the tie is unresolved — pick who goes.";
            }

            if ($row['is_over_quota']) {
                $blocking[] = "{$row['item_code']} {$row['title']}: {$row['chosen_count']} chosen for {$row['quota']} slot(s).";
            }

            if (! $row['is_complete'] && ! $row['is_over_quota'] && $row['slots_left'] > 0) {
                $warnings[] = "{$row['item_code']} {$row['title']}: {$row['slots_left']} of {$row['quota']} slot(s) unfilled.";
            }
        }

        return [
            'can_register' => $blocking === [],
            'blocking' => $blocking,
            'warnings' => $warnings,
            'chosen' => $sheet->sum('chosen_count'),
        ];
    }

    /**
     * Fill each item's slots with its top candidates, skipping anyone declined.
     *
     * A convenience for the common case, not a decision-maker: an item whose tie cannot be broken by
     * ranking alone is left untouched for the committee, rather than filled by whichever row sorted
     * first.
     *
     * @return array{filled: int, skipped_ties: list<string>}
     */
    public function autoFill(FestStateProgram $program, FestEvent $hubEvent, ?User $by = null): array
    {
        $batch = $this->nominations->openBatch($program, $hubEvent);
        abort_if($batch->isCertified(), 422, 'This batch is already registered with State.');

        $filled = 0;
        $skipped = [];

        foreach ($this->sheet($program, $hubEvent) as $row) {
            if ($row['unresolved_ties'] !== []) {
                $skipped[] = "{$row['item_code']} {$row['title']}";

                continue;
            }

            $slots = $row['slots_left'];

            foreach ($row['candidates'] as $candidate) {
                if ($slots <= 0) {
                    break;
                }

                if ($candidate['is_chosen'] || $candidate['is_declined']) {
                    continue;
                }

                $this->nominations->select($batch, $candidate, 'primary', $row['chosen_count'] + 1, $by);
                $slots--;
                $filled++;
            }
        }

        return ['filled' => $filled, 'skipped_ties' => $skipped];
    }
}
