<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateJudgeScore;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phase 6 of the State Kalotsav module — the judge's own view of the event.
 *
 * The defining rule: a judge sees chest numbers, never the Sahodaya, the School or the participant's
 * name. At State level a panel scoring a performance while knowing which Sahodaya sent it is the exact
 * failure the chest-number system exists to prevent, and it matters more here than at Sahodaya level
 * because the competing units are institutions with a standing rivalry.
 *
 * A judge sees only their own scores. Panel members do not see each other's marks before aggregation,
 * so one judge's number cannot anchor another's.
 */
class StateJudgePortalService
{
    public function __construct(private StateConductService $conduct) {}

    /**
     * Every item this judge is on, with how far through it they are.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assignments(int $judgeUserId): Collection
    {
        $assignments = StateJudgeAssignment::where('user_id', $judgeUserId)->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $events = StateFestEvent::whereIn('id', $assignments->pluck('state_event_id')->unique())
            ->get()->keyBy('id');
        $items = FestStateProgramItem::whereIn('id', $assignments->pluck('item_id')->unique())
            ->get()->keyBy('id');

        return $assignments->map(function (StateJudgeAssignment $assignment) use ($events, $items, $judgeUserId) {
            $event = $events->get($assignment->state_event_id);
            $targets = $event ? $this->targets($event, $assignment->item_id, $judgeUserId) : collect();

            return [
                'assignment_id' => $assignment->id,
                'event_id' => $assignment->state_event_id,
                'event' => $event?->name,
                'event_status' => $event?->status,
                'item_id' => $assignment->item_id,
                'item_code' => $assignment->item_code ?: $items->get($assignment->item_id)?->item_code,
                'item' => $items->get($assignment->item_id)?->title,
                'total' => $targets->count(),
                'scored' => $targets->whereNotNull('score')->count(),
                'submitted_at' => $assignment->submitted_at?->toDateTimeString(),
                'scoring_locked' => (bool) $event?->scoring_locked,
                'href' => "/portal/state-fest-judge/{$assignment->state_event_id}/items/{$assignment->item_id}",
            ];
        })->sortBy(['event', 'item_code'])->values();
    }

    public function assignmentFor(StateFestEvent $event, string $itemId, int $judgeUserId): ?StateJudgeAssignment
    {
        return StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('user_id', $judgeUserId)
            ->first();
    }

    /**
     * What the judge scores: one row per competing entry, identified by chest number only.
     *
     * Rows with no chest number are still listed — refusing to show them would hide entries from the
     * panel entirely — but they are flagged, because scoring an unnumbered entry means the judge is
     * identifying it some other way, which is exactly what should not be happening silently.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function targets(StateFestEvent $event, string $itemId, int $judgeUserId): Collection
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('status', 'approved')
            ->with('participants')
            ->get();

        $scores = StateJudgeScore::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('judge_user_id', $judgeUserId)
            ->get()->keyBy('participant_id');

        return $registrations
            ->map(function (StateFestRegistration $registration) use ($scores) {
                // A team is one competitive result, so it is scored once, against the member the
                // conduct service already anchors attendance and marks to.
                $anchor = $registration->participants->firstWhere('is_leader', true)
                    ?? $registration->participants->first(fn ($p) => $p->isCompeting())
                    ?? $registration->participants->first();

                if (! $anchor) {
                    return null;
                }

                $score = $scores->get($anchor->id);

                return [
                    'registration_id' => $registration->id,
                    'participant_id' => $anchor->id,
                    'chest_number' => $anchor->chest_number,
                    'team_size' => $registration->participants->filter(fn ($p) => $p->isCompeting())->count(),
                    'score' => $score?->score,
                    'grade' => $score?->grade,
                    'notes' => $score?->notes,
                ];
            })
            ->filter()
            // Called in chest-number order, unnumbered last — the same order the printed judge sheet
            // uses, so the screen and the paper agree.
            ->sortBy(fn (array $row) => [$row['chest_number'] === null ? 1 : 0, (int) $row['chest_number']])
            ->values();
    }

    /** @param  array{score: float, grade?: ?string, notes?: ?string}  $data */
    public function score(StateFestEvent $event, string $itemId, int $participantId, int $judgeUserId, array $data): void
    {
        $assignment = $this->assignmentFor($event, $itemId, $judgeUserId);

        if (! $assignment) {
            throw ValidationException::withMessages(['item_id' => 'You are not on the panel for this item.']);
        }

        $registration = StateFestRegistration::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->whereHas('participants', fn ($q) => $q->where('id', $participantId))
            ->firstOrFail();

        $this->conduct->enterJudgeScore($event, $registration, $participantId, $judgeUserId, $data);

        // Editing after submitting reopens the sheet rather than silently changing a submitted
        // score: the State office needs to see that the panel's declared-final sheet moved.
        if ($assignment->isSubmitted()) {
            $assignment->forceFill(['submitted_at' => null, 'submitted_count' => null])->save();
        }
    }

    /**
     * The judge declares their sheet finished.
     *
     * Refused while any competing entry is unscored. A partially scored sheet declared complete is
     * how a whole item ends up aggregated with a missing judge, and that is not recoverable after
     * results are published without reopening the item.
     *
     * @return array{submitted: int}
     */
    public function submit(StateFestEvent $event, string $itemId, int $judgeUserId): array
    {
        $assignment = $this->assignmentFor($event, $itemId, $judgeUserId);

        if (! $assignment) {
            throw ValidationException::withMessages(['item_id' => 'You are not on the panel for this item.']);
        }

        $targets = $this->targets($event, $itemId, $judgeUserId);
        $missing = $targets->whereNull('score');

        if ($missing->isNotEmpty()) {
            $chests = $missing->pluck('chest_number')->map(fn ($c) => $c ?: 'unnumbered')->take(8)->implode(', ');

            throw ValidationException::withMessages([
                'submit' => "{$missing->count()} entr".($missing->count() === 1 ? 'y is' : 'ies are')
                    ." still unscored: {$chests}.",
            ]);
        }

        $assignment->forceFill([
            'submitted_at' => now(),
            'submitted_count' => $targets->count(),
        ])->save();

        return ['submitted' => $targets->count()];
    }

    /**
     * Panel status for one item, for the State office rather than the judge: who has submitted and
     * who has not.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function panel(StateFestEvent $event, string $itemId): Collection
    {
        return StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('item_id', $itemId)->get()
            ->map(fn (StateJudgeAssignment $a) => [
                'assignment_id' => $a->id,
                'judge' => $a->user?->name ?? "User #{$a->user_id}",
                'submitted_at' => $a->submitted_at?->toDateTimeString(),
                'scored' => StateJudgeScore::where('state_event_id', $event->id)
                    ->where('item_id', $itemId)->where('judge_user_id', $a->user_id)
                    ->whereNotNull('score')->count(),
            ]);
    }
}
