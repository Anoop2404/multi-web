<?php

namespace App\Services\State\Fest;

use App\Models\State\StateEntryReview;
use App\Models\State\StateFestEvent;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateParticipationLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 4 of the State Kalotsav module — scrutiny.
 *
 * The pre-module workflow had two outcomes, approved and rejected, which loses the one a State
 * office needs most: "this is wrong, fix it and send it back". Without it a scrutineer must reject
 * a whole package over one missing date of birth, and a rejected intake is closed, so the Sahodaya
 * cannot correct it.
 *
 * Outcomes here:
 *   approved             — counts against the Sahodaya's slots
 *   rejected             — final, with a reason
 *   returned             — back to the Sahodaya to correct and resubmit
 *   documents_requested  — held pending evidence, not a judgement on the entry
 *
 * Every decision is appended to state_entry_reviews. The entry carries the current status for
 * queries; the log carries the history, because an appeal asks who decided what and when, and a
 * status column overwritten three times cannot answer that.
 */
class StateScrutinyService
{
    public const DECISIONS = ['approved', 'rejected', 'returned', 'documents_requested'];

    /** Outcomes that leave the entry still workable by the Sahodaya. */
    public const OPEN_DECISIONS = ['returned', 'documents_requested'];

    public function __construct(
        private StateParticipationLimitService $limits,
        private StateEventSettings $settings,
    ) {}

    /**
     * Record one decision on one entry.
     *
     * @param  array{note?: ?string, user_id?: ?int, user_name?: ?string, event?: ?StateFestEvent}  $context
     */
    public function decide(StateQualifierEntry $entry, string $decision, array $context = []): StateQualifierEntry
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => 'Unknown scrutiny decision.']);
        }

        $intake = $entry->intake;

        if ($intake && in_array($intake->status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'This intake is already finalised. Reopen it before changing an entry.',
            ]);
        }

        // Returning an entry has to say what is wrong, or the Sahodaya has nothing to act on.
        if (in_array($decision, self::OPEN_DECISIONS, true) && blank($context['note'] ?? null)) {
            throw ValidationException::withMessages([
                'note' => $decision === 'returned'
                    ? 'Say what needs correcting — the Sahodaya sees this note.'
                    : 'Say which document is needed.',
            ]);
        }

        if ($event = ($context['event'] ?? null)) {
            $this->assertScrutinyWindowOpen($event);
        }

        // Only approval consumes a slot, so only approval is checked against the allowance.
        if ($decision === 'approved') {
            $violations = $this->limits->validateEntryApproval($entry);

            if ($violations !== []) {
                throw ValidationException::withMessages(['decision' => $violations[0]]);
            }
        }

        return DB::connection('state')->transaction(function () use ($entry, $decision, $context, $intake) {
            $previous = $entry->status;

            $entry->forceFill([
                'status'              => $decision,
                'review_note'         => $context['note'] ?? null,
                'reviewed_at'         => now(),
                'reviewed_by_user_id' => $context['user_id'] ?? null,
            ])->save();

            StateEntryReview::create([
                'intake_id'          => $entry->intake_id,
                'entry_id'           => $entry->id,
                'state_id'           => $intake?->state_id,
                'decision'           => $decision,
                'previous_status'    => $previous,
                'note'               => $context['note'] ?? null,
                'decided_by_user_id' => $context['user_id'] ?? null,
                'decided_by_name'    => $context['user_name'] ?? null,
                'created_at'         => now(),
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Accept a reserve in place of an entry that will not compete.
     *
     * Two decisions in one action, recorded as two: the original is withdrawn and the reserve
     * approved, so the log reads as what happened rather than as an unexplained swap.
     */
    public function acceptReserve(
        StateQualifierEntry $original,
        StateQualifierEntry $reserve,
        array $context = [],
    ): StateQualifierEntry {
        if ($original->intake_id !== $reserve->intake_id) {
            throw ValidationException::withMessages([
                'reserve' => 'A reserve can only replace an entry from the same submission.',
            ]);
        }

        if ($original->item_id !== $reserve->item_id) {
            throw ValidationException::withMessages([
                'reserve' => 'The reserve must be entered for the same item.',
            ]);
        }

        return DB::connection('state')->transaction(function () use ($original, $reserve, $context) {
            $note = $context['note'] ?? "Replaced by reserve {$reserve->student_name}.";

            $this->decide($original, 'rejected', ['note' => $note] + $context);

            return $this->decide($reserve, 'approved', [
                'note' => "Accepted as reserve for {$original->student_name}.",
            ] + $context);
        });
    }

    /**
     * Finalise an intake once its entries have been worked through.
     *
     * An intake with entries still returned or awaiting documents is not finished — closing it
     * would strand them, since a finalised intake cannot be edited.
     */
    public function finalise(StateQualifierIntake $intake, array $context = []): StateQualifierIntake
    {
        $open = StateQualifierEntry::where('intake_id', $intake->id)
            ->whereIn('status', array_merge(['pending'], self::OPEN_DECISIONS))
            ->count();

        if ($open > 0) {
            throw ValidationException::withMessages([
                'intake' => "{$open} entr".($open === 1 ? 'y is' : 'ies are')
                    .' still pending, returned or awaiting documents. Decide those first.',
            ]);
        }

        $approved = StateQualifierEntry::where('intake_id', $intake->id)->where('status', 'approved')->count();

        $intake->forceFill([
            'status'       => $approved > 0 ? 'approved' : 'rejected',
            'reviewed_at'  => now(),
            'reviewed_by'  => $context['user_id'] ?? null,
            'review_notes' => $context['note'] ?? null,
        ])->save();

        StateEntryReview::create([
            'intake_id'          => $intake->id,
            'entry_id'           => null,
            'state_id'           => $intake->state_id,
            'decision'           => $approved > 0 ? 'approved' : 'rejected',
            'note'               => $context['note'] ?? null,
            'decided_by_user_id' => $context['user_id'] ?? null,
            'decided_by_name'    => $context['user_name'] ?? null,
            'created_at'         => now(),
        ]);

        return $intake->fresh();
    }

    /** Reopen a finalised intake so an entry can be revisited — itself a recorded decision. */
    public function reopen(StateQualifierIntake $intake, array $context = []): StateQualifierIntake
    {
        if (! in_array($intake->status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['intake' => 'This intake is not finalised.']);
        }

        $intake->forceFill(['status' => 'received'])->save();

        StateEntryReview::create([
            'intake_id'          => $intake->id,
            'state_id'           => $intake->state_id,
            'decision'           => 'reopened',
            'note'               => $context['note'] ?? null,
            'decided_by_user_id' => $context['user_id'] ?? null,
            'decided_by_name'    => $context['user_name'] ?? null,
            'created_at'         => now(),
        ]);

        return $intake->fresh();
    }

    private function assertScrutinyWindowOpen(StateFestEvent $event): void
    {
        if ($reason = $this->settings->windowClosedReason($event, StateEventSettings::WINDOW_SCRUTINY)) {
            throw ValidationException::withMessages(['decision' => $reason]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, StateEntryReview> */
    public function history(StateQualifierIntake $intake)
    {
        return StateEntryReview::where('intake_id', $intake->id)->orderByDesc('id')->get();
    }
}
