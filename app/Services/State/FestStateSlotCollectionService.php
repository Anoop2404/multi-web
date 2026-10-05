<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Get a proper list before State registration opens": a phase gate on top of the existing State
 * winner sheet (FestStateWinnerSheetService), not a parallel data store. Opening this for a hub
 * event auto-fills each item's top ranks the same way "Fill from the top" already does, and marks
 * those selections as awaiting the winning school's own response instead of treating a Sahodaya
 * pick as final. Approving requires every such slot to have been answered — accepted, or opted out
 * and the freed slot re-offered down the rank order — so the parent admin's "Register with State"
 * (FestStateWinnerRegistrationController::register()) always sends a list every school on it has
 * actually confirmed.
 *
 * A Sahodaya that never opens collection for a hub is unaffected: every selection it hand-picks the
 * old way keeps `school_response` null and none of the guards here ever engage.
 */
class FestStateSlotCollectionService
{
    public function __construct(
        private FestStateNominationService $nominations,
        private FestStateWinnerSheetService $sheets,
    ) {}

    /**
     * Auto-fills each item's top ranks (skipping unresolved ties, same as "Fill from the top") and
     * opens the school-response window on whatever it filled.
     *
     * @return array{filled: int, skipped_ties: list<string>}
     */
    public function open(FestStateProgram $program, FestEvent $hubEvent, User $by): array
    {
        return DB::transaction(function () use ($program, $hubEvent, $by) {
            FestEvent::whereKey($hubEvent->id)->lockForUpdate()->first();
            $hubEvent->refresh();

            abort_if($hubEvent->state_slot_collection_open, 422, 'Slot collection is already open for this event.');
            abort_if($hubEvent->state_slot_collection_approved_at, 422, 'This event\'s slot list was already approved.');

            $batch = $this->nominations->openBatch($program, $hubEvent);
            abort_if($batch->isCertified(), 422, 'This event is already registered with State.');

            $result = $this->sheets->autoFill($program, $hubEvent, $by);

            // Only rows this call (or an earlier hand-pick that predates collection) actually
            // created are untouched — autoFill() skips anyone already chosen or declined, so a
            // school response set on an earlier round is never overwritten here.
            $batch->selections()
                ->where('nomination_type', 'primary')
                ->where('status', 'selected')
                ->whereNull('school_response')
                ->update(['school_response' => 'pending']);

            $hubEvent->update([
                'state_slot_collection_open' => true,
                'state_slot_collection_opened_at' => now(),
            ]);

            return $result;
        });
    }

    /**
     * Re-run autoFill for newly increased item slot quotas while collection is open,
     * marking any newly added selections as 'pending' school response.
     */
    public function syncAdditionalSlots(FestStateProgram $program, FestEvent $hubEvent, User $by): array
    {
        return DB::transaction(function () use ($program, $hubEvent, $by) {
            $batch = $this->nominations->openBatch($program, $hubEvent);
            abort_if($batch->isCertified(), 422, 'This event is already registered with State.');

            $result = $this->sheets->autoFill($program, $hubEvent, $by);

            $batch->selections()
                ->where('nomination_type', 'primary')
                ->where('status', 'selected')
                ->whereNull('school_response')
                ->update(['school_response' => 'pending']);

            return $result;
        });
    }

    /**
     * A school's answer to one offered slot. Accepting just records who answered; opting out also
     * frees the slot and, same as a Sahodaya-recorded decline, re-runs "fill from the top" for that
     * item so the next rank is offered in turn — a chain that only stops once someone accepts or the
     * item runs out of candidates.
     */
    public function respond(FestStateNominationSelection $selection, string $action, ?string $reason, User $by): FestStateNominationSelection
    {
        abort_unless(in_array($action, ['accept', 'opt_out'], true), 422, 'Unknown response.');

        $batch = FestStateNominationBatch::findOrFail($selection->batch_id);
        $hubEvent = $batch->hubEvent;
        abort_unless($hubEvent, 404);
        abort_unless($hubEvent->state_slot_collection_open, 422, 'State slot collection is not open for this event.');
        abort_if($batch->isCertified(), 422, 'This has already been registered with State and can no longer be changed here.');

        return DB::transaction(function () use ($selection, $action, $reason, $by, $batch, $hubEvent) {
            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();
            $selection->refresh();

            abort_unless($selection->status === 'selected' && $selection->nomination_type === 'primary', 422, 'This slot is no longer open to respond to.');
            abort_unless($selection->school_response === 'pending', 422, 'Already responded to.');

            if ($action === 'accept') {
                $selection->update([
                    'school_response' => 'accepted',
                    'school_responded_by' => $by->id,
                    'school_responded_by_name' => $by->name,
                    'school_responded_at' => now(),
                ]);

                return $selection->fresh();
            }

            if (trim((string) $reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Say why this winner is not going. The Sahodaya will see this reason.',
                ]);
            }

            $selection->update([
                'status' => FestStateWinnerSheetService::DECLINED,
                'skip_reason' => $reason,
                'school_response' => 'opted_out',
                'school_responded_by' => $by->id,
                'school_responded_by_name' => $by->name,
                'school_responded_at' => now(),
            ]);

            $program = FestStateProgram::findOrFail($batch->state_program_id);
            $this->sheets->autoFill($program, $hubEvent, $by);

            // Whichever row autoFill() just created for this item to fill the freed slot — tag it
            // as waiting on its own school, and note the chain for the admin's list.
            $batch->selections()
                ->where('item_id', $selection->item_id)
                ->where('nomination_type', 'primary')
                ->where('status', 'selected')
                ->whereNull('school_response')
                ->update(['school_response' => 'pending', 'replaces_selection_id' => $selection->id]);

            return $selection->fresh();
        });
    }

    /**
     * Locks the list: every offered slot must have been answered (accepted, or opted out and its
     * replacement in turn resolved), and the sheet's usual tie/over-quota checks must be clear.
     * Approving does not itself certify or send anything — it only clears the way for the existing
     * "Register with State" to run against a list nobody is still waiting on.
     *
     * @return array{approved_count: int, warnings: list<string>}
     */
    public function approve(FestStateProgram $program, FestEvent $hubEvent, User $by): array
    {
        abort_unless($hubEvent->state_slot_collection_open, 422, 'Slot collection was never opened for this event.');
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'This event\'s slot list was already approved.');

        $batch = $this->nominations->openBatch($program, $hubEvent);
        abort_if($batch->isCertified(), 422, 'This event is already registered with State.');

        $readiness = $this->sheets->readiness($program, $hubEvent);

        $pendingCount = $batch->selections()
            ->where('nomination_type', 'primary')
            ->where('status', 'selected')
            ->where('school_response', 'pending')
            ->count();

        $blocking = $readiness['blocking'];
        if ($pendingCount > 0) {
            $blocking[] = "{$pendingCount} slot(s) are still waiting on a school's response.";
        }

        abort_if($blocking !== [], 422, implode(' ', $blocking));

        $hubEvent->update([
            'state_slot_collection_approved_at' => now(),
            'state_slot_collection_approved_by' => $by->id,
        ]);

        return ['approved_count' => $readiness['chosen'], 'warnings' => $readiness['warnings']];
    }

    /**
     * The parent admin's view: the usual winner sheet, with each chosen row's school response and
     * a per-item response tally added.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(FestStateProgram $program, FestEvent $hubEvent): Collection
    {
        $sheet = $this->sheets->sheet($program, $hubEvent);
        $batch = $this->nominations->openBatch($program, $hubEvent);
        $byItem = $batch->selections()
            ->where('nomination_type', 'primary')
            ->get()
            ->groupBy('item_id');

        return $sheet->map(function (array $row) use ($byItem) {
            $rows = $byItem->get($row['item_id'], collect());

            $row['school_responses'] = [
                'accepted' => $rows->where('status', 'selected')->where('school_response', 'accepted')->count(),
                'pending' => $rows->where('status', 'selected')->where('school_response', 'pending')->count(),
                'opted_out' => $rows->where('school_response', 'opted_out')->count(),
            ];

            $row['chosen'] = collect($row['chosen'])->map(function (array $c) use ($rows) {
                $selection = $rows->firstWhere('id', $c['id']);
                $c['school_response'] = $selection?->school_response;
                $c['school_responded_by_name'] = $selection?->school_responded_by_name;
                $c['school_responded_at'] = $selection?->school_responded_at?->toIso8601String();

                return $c;
            })->values()->all();

            $row['declined'] = collect($row['declined'])->map(function (array $c) use ($rows) {
                $selection = $rows->firstWhere('id', $c['id']);
                $c['school_response'] = $selection?->school_response;
                $c['school_responded_by_name'] = $selection?->school_responded_by_name;
                $c['school_responded_at'] = $selection?->school_responded_at?->toIso8601String();

                return $c;
            })->values()->all();

            return $row;
        });
    }

    /**
     * One school's own list: every slot it has been offered for this hub, in whatever state it is
     * in. Only rows created by this flow (school_response not null) show up — a Sahodaya hand-pick
     * made outside it is not this school's to answer.
     *
     * @return list<array<string, mixed>>
     */
    public function forSchool(FestStateProgram $program, FestEvent $hubEvent, string $schoolId): array
    {
        $batch = $this->nominations->openBatch($program, $hubEvent);

        return $batch->selections()
            ->where('nomination_type', 'primary')
            ->where('school_id', $schoolId)
            ->whereNotNull('school_response')
            ->get()
            ->sortBy('item_title')
            ->values()
            ->map(fn (FestStateNominationSelection $s) => [
                'id' => $s->id,
                'item_id' => $s->item_id,
                'item_code' => $s->item_code,
                'item_title' => $s->item_title,
                'student_name' => $s->student_name,
                'class_name' => $s->class_name,
                'source_position' => $s->source_position,
                'status' => $s->status,
                'school_response' => $s->school_response,
                'opt_out_reason' => $s->status === FestStateWinnerSheetService::DECLINED ? $s->skip_reason : null,
                'responded_at' => $s->school_responded_at?->toIso8601String(),
                'responded_by_name' => $s->school_responded_by_name,
            ])
            ->all();
    }
}
