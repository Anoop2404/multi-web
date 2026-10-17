<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
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

            $hubEvent->update(['state_slot_collection_open' => true, 'state_slot_collection_opened_at' => now()]);
            $result = $this->sheets->autoFill($program, $hubEvent, $by);

            // Only rows this call (or an earlier hand-pick that predates collection) actually
            // created are untouched — autoFill() skips anyone already chosen or declined, so a
            // school response set on an earlier round is never overwritten here.
            $batch->selections()
                ->where('nomination_type', 'primary')
                ->where('status', 'selected')
                ->whereNull('school_response')
                ->update(['school_response' => 'pending']);

            return $result;
        });
    }

    /**
     * Re-run autoFill for newly increased item slot quotas while collection is open,
     * marking any newly added selections as 'pending' school response.
     */
    public function syncAdditionalSlots(FestStateProgram $program, FestEvent $hubEvent, User $by): array
    {
        abort_unless($hubEvent->state_slot_collection_open, 422, 'Open collection first.');
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'This slot list is already approved.');
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
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');
        abort_if($batch->isCertified(), 422, 'This has already been registered with State and can no longer be changed here.');

        return DB::transaction(function () use ($selection, $action, $reason, $by, $batch, $hubEvent) {
            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();
            $selection->refresh();
            $batch->refresh();
            abort_if($batch->isCertified(), 422, 'This has already been registered with State.');

            abort_unless($selection->status === 'selected' && $selection->nomination_type === 'primary', 422, 'This slot is no longer open to respond to.');
            abort_unless($selection->school_response === 'pending', 422, 'Already responded to.');

            $hubEvent->refresh();
            abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');
            if ($action === 'accept') {
                $pool = app(FestStateCollectionCandidates::class)->pool(FestStateProgram::findOrFail($batch->state_program_id), $hubEvent);
                abort_unless($pool->contains(fn ($candidate) => $candidate['item_id'] === $selection->item_id
                    && ($candidate['participant_id'] === $selection->participant_id
                        || ($selection->mark_id && $candidate['mark_id'] === $selection->mark_id)
                        || ($selection->registration_id && $candidate['registration_id'] === $selection->registration_id))),
                    422, 'This offer is no longer eligible. Ask the Sahodaya to review the entry.');
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
            $this->sheets->autoFill($program, $hubEvent, $by, $selection->item_id);

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
     * Cancel an earlier opt-out and reclaim the offered slot for the school.
     * Revokes any pending downstream replacement that was auto-offered.
     */
    public function cancelOptOut(FestStateNominationSelection $selection, User $by): FestStateNominationSelection
    {
        $batch = FestStateNominationBatch::findOrFail($selection->batch_id);
        $hubEvent = $batch->hubEvent;
        abort_unless($hubEvent, 404);
        abort_unless($hubEvent->state_slot_collection_open, 422, 'State slot collection is not open for this event.');
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');
        abort_if($batch->isCertified(), 422, 'This event is already registered with State.');

        abort_unless(
            $selection->school_response === 'opted_out' || $selection->status === FestStateWinnerSheetService::DECLINED,
            422,
            'This slot is not opted out.'
        );

        return DB::transaction(function () use ($selection, $by, $batch, $hubEvent) {
            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();
            $selection->refresh();
            $batch->refresh();
            $hubEvent->refresh();

            abort_if($batch->isCertified(), 422, 'This event is already registered with State.');
            abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');
            abort_unless($selection->school_response === 'opted_out' || $selection->status === FestStateWinnerSheetService::DECLINED, 422, 'This slot is not opted out.');

            $program = FestStateProgram::findOrFail($batch->state_program_id);
            $pool = app(FestStateCollectionCandidates::class)->pool($program, $hubEvent);
            $eligible = $pool->contains(fn ($candidate) => $candidate['item_id'] === $selection->item_id
                && (($selection->participant_id && $candidate['participant_id'] === $selection->participant_id)
                    || ($selection->mark_id && $candidate['mark_id'] === $selection->mark_id)
                    || ($selection->registration_id && $candidate['registration_id'] === $selection->registration_id)));

            abort_unless($eligible, 422, 'This candidate is no longer eligible.');

            // Check if there was a replacement selection generated from this opt-out
            $replacement = $batch->selections()
                ->where('replaces_selection_id', $selection->id)
                ->where('status', 'selected')
                ->first();

            if ($replacement) {
                if ($replacement->school_response === 'accepted') {
                    throw ValidationException::withMessages([
                        'slot' => "Cannot cancel opt-out because replacement candidate {$replacement->student_name} from {$replacement->school_name} has already accepted and confirmed this slot. Please contact Sahodaya admin to adjust.",
                    ]);
                }

                // If replacement is still pending, delete it to free the slot
                $replacement->delete();
            } else {
                // If no direct replaces_selection_id, check item quota
                $item = FestStateProgramItem::findOrFail($selection->item_id);
                $quota = $this->nominations->itemQuota($item->id, $selection->item_code, (string) $hubEvent->id) ?? (int) ($item->qualify_count ?: 0);
                $chosenCount = $batch->selections()
                    ->where('item_id', $selection->item_id)
                    ->where('nomination_type', 'primary')
                    ->where('status', 'selected')
                    ->count();

                if ($quota !== null && $chosenCount >= $quota) {
                    throw ValidationException::withMessages([
                        'slot' => "Cannot cancel opt-out. All {$quota} slot(s) for this item are currently filled. Please contact Sahodaya admin to adjust.",
                    ]);
                }
            }

            $selection->update([
                'status' => 'selected',
                'school_response' => 'pending',
                'skip_reason' => null,
                'school_responded_by' => null,
                'school_responded_by_name' => null,
                'school_responded_at' => null,
            ]);

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
            ->where(fn ($query) => $query->whereNull('school_response')->orWhere('school_response', '!=', 'accepted'))
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
        $sheet = $this->sheets->sheet($program, $hubEvent, ['slot_collection' => true, 'include_pending_items' => true]);
        $batch = $this->nominations->openBatch($program, $hubEvent);
        $byItem = $batch->selections()
            ->where('nomination_type', 'primary')
            ->get()
            ->groupBy('item_id');

        $labels = \App\Support\FestClassGroupScheme::labels(null, $hubEvent->rootEvent());
        return $sheet->map(function (array $row) use ($byItem, $labels, $hubEvent) {
            $displayItem = new \App\Models\FestEventItem($row);
            $row['category_label'] = \App\Support\FestItemCategoryLabel::resolve($displayItem, $labels);
            $row['gender_label'] = \App\Support\FestItemCategoryLabel::genderLabel($row['gender']) ?? 'Open';
            $row['type_label'] = \App\Support\FestItemCategoryLabel::typeLabel($row['participant_type']);
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

            $row['chosen'] = collect($row['chosen'])->map(function ($choice) use ($row) {
                $choice['is_eligible'] = collect($row['candidates'])->contains(fn ($candidate) =>
                    ($choice['participant_id'] && $candidate['participant_id'] === $choice['participant_id'])
                    || ($choice['mark_id'] && $candidate['mark_id'] === $choice['mark_id'])
                    || (! empty($choice['registration_id']) && ! empty($candidate['registration_id']) && $candidate['registration_id'] === $choice['registration_id']));
                return $choice;
            })->all();
            $validOffers = collect($row['chosen'])->where('is_eligible', true);
            $accepted = $validOffers->where('school_response', 'accepted')->count();
            $pending = $validOffers->filter(fn ($choice) => $choice['school_response'] !== 'accepted')->count();
            $invalid = count($row['chosen']) - $validOffers->count();
            $notOffered = collect($row['candidates'])->filter(fn ($candidate) => ! $candidate['is_chosen'] && ! $candidate['is_declined']
                && ($candidate['direct_entry'] || $candidate['source_position'] <= 2 || $row['declined'] !== []))->count();
            $status = match (true) {
                $row['unresolved_ties'] !== [] || $row['is_over_quota'] || $invalid > 0 => 'attention',
                $row['candidates'] === [] && $row['chosen'] === [] && $row['declined'] === [] => 'awaiting_eligibility',
                ! $hubEvent->state_slot_collection_open => 'not_open',
                $pending > 0 => 'pending',
                $row['quota'] > 0 && $accepted >= $row['quota'] => 'complete',
                $notOffered > 0 && $row['slots_left'] > 0 => 'not_offered',
                default => 'unfilled',
            };
            $row['slot_status'] = $status;
            $row['slot_progress'] = [
                'accepted' => $accepted, 'pending' => $pending,
                'unfilled' => max(0, $row['quota'] - $row['chosen_count']),
                'not_offered' => min($notOffered, $row['slots_left']), 'invalid' => $invalid,
                'percent' => $row['quota'] > 0 ? min(100, (int) round(100 * $accepted / $row['quota'])) : 0,
            ];

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

        $offered = $batch->selections()
            ->where('nomination_type', 'primary')
            ->where('school_id', $schoolId)
            ->whereNotNull('school_response')
            ->get()
            ->sortBy('item_title')
            ->values()
            ->map(fn (FestStateNominationSelection $s) => [
                'id' => $s->id,
                'item_id' => $s->item_id,
                'participant_id' => $s->participant_id,
                'registration_id' => $s->registration_id,
                'mark_id' => $s->mark_id,
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

        $sheet = $this->summary($program, $hubEvent);
        foreach ($offered as &$offer) {
            $row = $sheet->firstWhere('item_id', $offer['item_id']);
            $offer['category_label'] = $row['category_label'] ?? null;
            $offer['gender_label'] = $row['gender_label'] ?? 'Open';
            $offer['direct_entry'] = $row['direct_entry'] ?? false;
            $matchedCandidate = collect($row['candidates'] ?? [])->first(fn ($candidate) =>
                ($offer['participant_id'] && $candidate['participant_id'] === $offer['participant_id'])
                || ($offer['mark_id'] && $candidate['mark_id'] === $offer['mark_id'])
                || (! empty($offer['registration_id']) && ! empty($candidate['registration_id']) && $candidate['registration_id'] === $offer['registration_id']));
            $matchedChosen = collect($row['chosen'] ?? [])->firstWhere('id', $offer['id']);
            $offer['photo_url'] = $matchedCandidate['photo_url'] ?? $matchedChosen['photo_url'] ?? null;
            $stillEligible = (bool) $matchedCandidate;
            $offer['can_respond'] = $stillEligible && $hubEvent->state_slot_collection_open && ! $hubEvent->state_slot_collection_approved_at
                && $offer['status'] === 'selected' && $offer['school_response'] === 'pending';
            $offer['can_cancel_opt_out'] = $stillEligible && $hubEvent->state_slot_collection_open && ! $hubEvent->state_slot_collection_approved_at
                && ! $batch->isCertified()
                && $offer['school_response'] === 'opted_out'
                && $this->canCancelOptOut($batch, $offer['id'], $offer['item_id'], $row['quota'] ?? 2);
        }
        unset($offer);
        foreach ($sheet as $row) {
            $unofferedCandidates = collect($row['candidates'] ?? [])
                ->filter(fn ($c) => ! $c['is_chosen'] && ! $c['is_declined'])
                ->values();

            foreach ($row['candidates'] as $candidate) {
                if ($candidate['school_id'] !== $schoolId || $candidate['is_chosen'] || $candidate['is_declined']) {
                    continue;
                }

                $queueIndex = $unofferedCandidates->search(fn ($c) =>
                    ($candidate['participant_id'] && $c['participant_id'] === $candidate['participant_id'])
                    || ($candidate['mark_id'] && $c['mark_id'] === $candidate['mark_id'])
                    || (! empty($candidate['registration_id']) && ! empty($c['registration_id']) && $candidate['registration_id'] === $c['registration_id']));

                $waitlistPosition = $queueIndex !== false ? $queueIndex + 1 : null;
                $optOutsNeeded = $waitlistPosition ?: 1;

                $offered[] = [
                    'id' => null, 'key' => 'waiting-'.($candidate['participant_id'] ?? $candidate['registration_id'] ?? $candidate['mark_id']),
                    'item_id' => $row['item_id'], 'item_code' => $row['item_code'], 'item_title' => $row['title'],
                    'category_label' => $row['category_label'], 'gender_label' => $row['gender_label'],
                    'student_name' => $candidate['student_name'], 'class_name' => $candidate['class_name'],
                    'photo_url' => $candidate['photo_url'] ?? null,
                    'source_position' => $candidate['source_position'], 'direct_entry' => $candidate['direct_entry'],
                    'status' => 'waiting', 'school_response' => 'waiting', 'can_respond' => false,
                    'opt_out_reason' => null, 'responded_at' => null, 'responded_by_name' => null,
                    'waitlist_position' => $waitlistPosition,
                    'opt_outs_needed' => $optOutsNeeded,
                    'quota' => $row['quota'] ?? 2,
                    'is_next_in_line' => $waitlistPosition === 1,
                ];
            }
        }

        return $offered;
    }

    /**
     * Send an offer to a candidate (for resolving ties or manual offers).
     */
    public function sendOffer(FestStateProgram $program, FestEvent $hubEvent, array $candidate, User $by): FestStateNominationSelection
    {
        abort_unless($hubEvent->state_slot_collection_open, 422, 'State slot collection is not open for this event.');
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');

        return DB::transaction(function () use ($program, $hubEvent, $candidate, $by) {
            $batch = $this->nominations->openBatch($program, $hubEvent);
            abort_if($batch->isCertified(), 422, 'This event is already registered with State.');

            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();

            $item = FestStateProgramItem::findOrFail($candidate['item_id']);
            $quota = $this->nominations->itemQuota($item->id, $candidate['item_code'] ?? null, (string) $hubEvent->id) ?? (int) ($item->qualify_count ?: 0);
            $chosenCount = $batch->selections()
                ->where('item_id', $candidate['item_id'])
                ->where('nomination_type', 'primary')
                ->where('status', 'selected')
                ->count();

            if ($quota !== null && $chosenCount >= $quota) {
                throw ValidationException::withMessages([
                    'slot' => "Cannot send offer. All {$quota} slot(s) for {$candidate['item_code']} are currently filled.",
                ]);
            }

            // Check if there is an existing declined row for this candidate in the batch
            $existingDeclined = $batch->selections()
                ->where('item_id', $candidate['item_id'])
                ->where('status', FestStateWinnerSheetService::DECLINED)
                ->where(function ($q) use ($candidate) {
                    $q->when(! empty($candidate['mark_id']), fn ($sq) => $sq->orWhere('mark_id', $candidate['mark_id']))
                      ->when(! empty($candidate['participant_id']), fn ($sq) => $sq->orWhere('participant_id', $candidate['participant_id']))
                      ->when(! empty($candidate['registration_id']), fn ($sq) => $sq->orWhere('registration_id', $candidate['registration_id']));
                })
                ->first();

            if ($existingDeclined) {
                $existingDeclined->update([
                    'status' => 'selected',
                    'nomination_type' => 'primary',
                    'school_response' => 'pending',
                    'skip_reason' => null,
                    'priority_order' => $chosenCount + 1,
                    'selected_by' => $by->id,
                    'school_responded_by' => null,
                    'school_responded_by_name' => null,
                    'school_responded_at' => null,
                ]);

                return $existingDeclined->fresh();
            }

            $selection = $this->nominations->select($batch, $candidate, 'primary', $chosenCount + 1, $by);
            $selection->update(['school_response' => 'pending']);

            return $selection->fresh();
        });
    }

    /**
     * Alter an offer or selection: reopen an opted-out entry, accept on school's behalf,
     * record opt-out with reason, or revoke the offer.
     */
    public function alterSelection(FestStateProgram $program, FestEvent $hubEvent, FestStateNominationSelection $selection, string $action, ?string $reason, User $by): ?FestStateNominationSelection
    {
        abort_unless(in_array($action, ['reopen', 'accept', 'opt_out', 'revoke'], true), 422, 'Unknown action.');
        abort_unless($hubEvent->state_slot_collection_open, 422, 'State slot collection is not open for this event.');
        abort_if($hubEvent->state_slot_collection_approved_at, 422, 'The State slot list is already approved.');

        $batch = FestStateNominationBatch::findOrFail($selection->batch_id);
        abort_if($batch->isCertified(), 422, 'This has already been registered with State and can no longer be changed here.');
        abort_unless($batch->hub_event_id === $hubEvent->id, 404);

        return DB::transaction(function () use ($selection, $action, $reason, $by, $batch, $program, $hubEvent) {
            FestStateNominationBatch::whereKey($batch->id)->lockForUpdate()->first();
            $selection->refresh();
            $batch->refresh();

            if ($action === 'revoke') {
                abort_unless($selection->status === 'selected', 422, 'Can only revoke an active selection.');
                $selection->delete();

                return null;
            }

            if ($action === 'accept') {
                abort_unless($selection->status === 'selected', 422, 'Can only accept an active selection.');
                $pool = app(FestStateCollectionCandidates::class)->pool($program, $hubEvent);
                $eligible = $pool->contains(fn ($candidate) => $candidate['item_id'] === $selection->item_id
                    && (($selection->participant_id && $candidate['participant_id'] === $selection->participant_id)
                        || ($selection->mark_id && $candidate['mark_id'] === $selection->mark_id)
                        || ($selection->registration_id && $candidate['registration_id'] === $selection->registration_id)));
                abort_unless($eligible, 422, 'This offer is no longer eligible. Review the entry.');

                $selection->update([
                    'school_response' => 'accepted',
                    'school_responded_by' => $by->id,
                    'school_responded_by_name' => $by->name.' (Sahodaya)',
                    'school_responded_at' => now(),
                ]);

                return $selection->fresh();
            }

            if ($action === 'opt_out') {
                abort_unless($selection->status === 'selected', 422, 'Can only opt out an active selection.');
                if (trim((string) $reason) === '') {
                    throw ValidationException::withMessages([
                        'reason' => 'Say why this winner is not going. The reason is recorded for State submission.',
                    ]);
                }

                $selection->update([
                    'status' => FestStateWinnerSheetService::DECLINED,
                    'skip_reason' => $reason,
                    'school_response' => 'opted_out',
                    'school_responded_by' => $by->id,
                    'school_responded_by_name' => $by->name.' (Sahodaya)',
                    'school_responded_at' => now(),
                ]);

                $this->sheets->autoFill($program, $hubEvent, $by, $selection->item_id);

                $batch->selections()
                    ->where('item_id', $selection->item_id)
                    ->where('nomination_type', 'primary')
                    ->where('status', 'selected')
                    ->whereNull('school_response')
                    ->update(['school_response' => 'pending', 'replaces_selection_id' => $selection->id]);

                return $selection->fresh();
            }

            if ($action === 'reopen') {
                abort_unless($selection->status === FestStateWinnerSheetService::DECLINED, 422, 'Only opted-out or declined entries can be reopened.');

                $item = FestStateProgramItem::findOrFail($selection->item_id);
                $quota = $this->nominations->itemQuota($item->id, $selection->item_code, (string) $hubEvent->id) ?? (int) ($item->qualify_count ?: 0);
                $chosenCount = $batch->selections()
                    ->where('item_id', $selection->item_id)
                    ->where('nomination_type', 'primary')
                    ->where('status', 'selected')
                    ->count();

                if ($quota !== null && $chosenCount >= $quota) {
                    throw ValidationException::withMessages([
                        'slot' => "Cannot reopen offer. All {$quota} slot(s) for this item are currently filled. Free a slot first to reopen this offer.",
                    ]);
                }

                $pool = app(FestStateCollectionCandidates::class)->pool($program, $hubEvent);
                $eligible = $pool->contains(fn ($candidate) => $candidate['item_id'] === $selection->item_id
                    && (($selection->participant_id && $candidate['participant_id'] === $selection->participant_id)
                        || ($selection->mark_id && $candidate['mark_id'] === $selection->mark_id)
                        || ($selection->registration_id && $candidate['registration_id'] === $selection->registration_id)));

                abort_unless($eligible, 422, 'This candidate is no longer eligible.');

                $selection->update([
                    'status' => 'selected',
                    'school_response' => 'pending',
                    'skip_reason' => null,
                    'school_responded_by' => null,
                    'school_responded_by_name' => null,
                    'school_responded_at' => null,
                    'priority_order' => $chosenCount + 1,
                ]);

                return $selection->fresh();
            }

            return null;
        });
    }

    private function canCancelOptOut(FestStateNominationBatch $batch, ?int $selectionId, int|string $itemId, int $quota): bool
    {
        if (! $selectionId) {
            return false;
        }

        $replacement = $batch->selections()
            ->where('replaces_selection_id', $selectionId)
            ->where('status', 'selected')
            ->first();

        if ($replacement) {
            return $replacement->school_response !== 'accepted';
        }

        $chosenCount = $batch->selections()
            ->where('item_id', $itemId)
            ->where('nomination_type', 'primary')
            ->where('status', 'selected')
            ->count();

        return $chosenCount < $quota;
    }
}
