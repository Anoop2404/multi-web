<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateAppeal;
use App\Models\State\StateConductAudit;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateItemResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 7 of the State Kalotsav module — appeals.
 *
 * An appeal is the only sanctioned way a published result changes. Upholding one therefore does
 * three things at once, and doing fewer leaves the event inconsistent: it records the decision, it
 * moves the item's result back to provisional so the corrected ranking is not public until the
 * office republishes it, and it marks every certificate printed from that item stale.
 *
 * The fee follows the outcome — upheld refunds, dismissed forfeits — because that is the rule
 * everywhere a Kalotsavam appeal fee exists, and leaving it to a separate manual step is how a
 * refund gets forgotten.
 */
class StateAppealService
{
    public function __construct(private StateCertificateService $certificates) {}

    /** @param array<string, mixed> $data */
    public function submit(StateFestEvent $event, array $data): StateAppeal
    {
        return StateAppeal::create([
            'id' => (string) Str::uuid(),
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'sahodaya_id' => $data['sahodaya_id'] ?? null,
            'item_id' => $data['item_id'] ?? null,
            'item_code' => $data['item_code'] ?? null,
            'registration_id' => $data['registration_id'] ?? null,
            'participant_id' => $data['participant_id'] ?? null,
            'participant_name' => $data['participant_name'] ?? null,
            'school_name' => $data['school_name'] ?? null,
            'grounds' => $data['grounds'],
            'evidence_path' => $data['evidence_path'] ?? null,
            'fee_amount' => $data['fee_amount'] ?? 0,
            'fee_status' => $data['fee_amount'] ?? 0 ? 'paid' : 'waived',
            'status' => 'submitted',
        ]);
    }

    /**
     * Decide an appeal.
     *
     * @param  array{outcome: string, notes?: ?string, summary?: ?string, user_id?: ?int, user_name?: ?string}  $data
     * @return array{appeal: StateAppeal, certificates_stale: int, result_reopened: bool}
     */
    public function decide(StateFestEvent $event, StateAppeal $appeal, array $data): array
    {
        if (! $appeal->isOpen()) {
            throw ValidationException::withMessages(['appeal' => 'This appeal has already been decided.']);
        }

        if (! in_array($data['outcome'], ['upheld', 'dismissed'], true)) {
            throw ValidationException::withMessages(['outcome' => 'An appeal is either upheld or dismissed.']);
        }

        // A decision without reasons cannot be defended later, and an appeal is precisely the thing
        // that gets questioned.
        if (blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages(['notes' => 'Record the reasons for the decision.']);
        }

        return DB::connection('state')->transaction(function () use ($event, $appeal, $data) {
            $appeal->forceFill([
                'status' => $data['outcome'],
                'review_notes' => $data['notes'],
                'outcome_summary' => $data['summary'] ?? null,
                // Upheld refunds, dismissed forfeits — a waived fee stays waived.
                'fee_status' => $appeal->fee_status === 'waived'
                    ? 'waived'
                    : ($data['outcome'] === 'upheld' ? 'refunded' : 'forfeited'),
                'decided_by_user_id' => $data['user_id'] ?? null,
                'decided_by_name' => $data['user_name'] ?? null,
                'decided_at' => now(),
            ])->save();

            $stale = 0;
            $reopened = false;

            if ($data['outcome'] === 'upheld' && $appeal->item_id) {
                $reopened = $this->reopenResult($event, $appeal);
                $stale = $this->certificates->markItemStale($event, $appeal->item_id, 'Appeal upheld');
            }

            StateConductAudit::create([
                'state_event_id' => $event->id,
                'state_id' => $event->state_id,
                'kind' => 'result',
                'item_id' => $appeal->item_id,
                'item_code' => $appeal->item_code,
                'value_from' => 'appeal:'.$appeal->id,
                'value_to' => $data['outcome'],
                'reason' => $data['notes'],
                'changed_by_user_id' => $data['user_id'] ?? null,
                'changed_by_name' => $data['user_name'] ?? null,
                'created_at' => now(),
            ]);

            return ['appeal' => $appeal->fresh(), 'certificates_stale' => $stale, 'result_reopened' => $reopened];
        });
    }

    /**
     * Take the item's result off public view so the corrected ranking is not published by accident.
     *
     * A locked result is left alone: locking is a deliberate statement that the result is final, and
     * an appeal against it needs a person to unlock it first rather than the system doing so quietly.
     */
    private function reopenResult(StateFestEvent $event, StateAppeal $appeal): bool
    {
        $result = StateItemResult::where('state_event_id', $event->id)
            ->where('item_id', $appeal->item_id)->first();

        if (! $result || $result->isLocked() || $result->status === StateItemResult::DRAFT) {
            return false;
        }

        $result->forceFill([
            'status' => StateItemResult::PROVISIONAL,
            'published_at' => null,
            'notes' => trim(($result->notes ? $result->notes."\n" : '')."Reopened by appeal {$appeal->id}."),
        ])->save();

        return true;
    }

    /** Adjust one participant's mark as the outcome of an upheld appeal. */
    public function adjustMark(StateFestEvent $event, StateAppeal $appeal, float $score, array $context = []): StateFestMark
    {
        if ($appeal->status !== 'upheld') {
            throw ValidationException::withMessages([
                'appeal' => 'Uphold the appeal before changing a mark under it.',
            ]);
        }

        $mark = StateFestMark::where('state_event_id', $event->id)
            ->where('participant_id', $appeal->participant_id)->firstOrFail();

        $previous = $mark->score;
        $mark->forceFill(['score' => $score, 'status' => 'adjusted'])->save();

        StateConductAudit::create([
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'kind' => 'mark',
            'registration_id' => $mark->registration_id,
            'participant_id' => $mark->participant_id,
            'item_id' => $appeal->item_id,
            'item_code' => $appeal->item_code,
            'value_from' => (string) $previous,
            'value_to' => (string) $score,
            'reason' => "Appeal {$appeal->id} upheld",
            'changed_by_user_id' => $context['user_id'] ?? null,
            'changed_by_name' => $context['user_name'] ?? null,
            'created_at' => now(),
        ]);

        return $mark->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public function listFor(StateFestEvent $event)
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)->pluck('title', 'id');

        return StateAppeal::where('state_event_id', $event->id)->orderByDesc('created_at')->get()
            ->map(fn (StateAppeal $a) => [
                'id' => $a->id,
                'item_code' => $a->item_code,
                'item_name' => $items[$a->item_id] ?? null,
                'participant' => $a->participant_name,
                'school' => $a->school_name,
                'grounds' => $a->grounds,
                'fee_amount' => $a->fee_amount,
                'fee_status' => $a->fee_status,
                'status' => $a->status,
                'review_notes' => $a->review_notes,
                'decided_by' => $a->decided_by_name,
                'decided_at' => $a->decided_at?->toDateTimeString(),
                'submitted_at' => $a->created_at?->toDateTimeString(),
            ]);
    }
}
