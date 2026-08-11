<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateJudgeScore;

/**
 * State Event Conduct, Phase 4 (docs/STATE_EVENT_CONDUCT_PLAN.md) — mirrors
 * App\Services\Events\FestJudgeScoreService. Each assigned judge submits independently;
 * once every judge assigned to the item has scored a participant, syncAggregatedMark()
 * averages the scores and writes the canonical StateFestMark row. This averaging-once-
 * everyone's-in step is the "double verification" the conduct gap list asked for.
 *
 * Deliberately does not yet recompute school-level aggregate points or dispatch a
 * scoreboard-updated event the way the tenant version does — there's no State equivalent of
 * EventContext yet; that's Phase 5 (results publish) work, not this phase's.
 */
class StateJudgeScoreService
{
    public function __construct(private StateGradePointService $gradePoints) {}

    /** @return array{message: string} */
    public function save(StateFestEvent $event, array $data, int $judgeUserId): array
    {
        $participant = StateFestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->state_event_id !== $event->id, 403);
        abort_if(in_array($participant->registration->status, ['rejected', 'withdrawn'], true), 422, 'Scores cannot be entered for a withdrawn or rejected registration.');
        abort_if(
            $participant->registration->participants()->min('id') !== $participant->id,
            422,
            'Score the team or group once using its first listed participant.',
        );

        $assigned = StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('item_id', $data['item_id'])
            ->where('user_id', $judgeUserId)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned as a judge for this item.');

        if (! empty($data['score']) && empty($data['grade'])) {
            $data['grade'] = $this->gradePoints->resolveGradeFromScore($event, (float) $data['score']);
        }

        StateJudgeScore::updateOrCreate(
            [
                'item_id'       => $data['item_id'],
                'participant_id' => $data['participant_id'],
                'judge_user_id' => $judgeUserId,
            ],
            [
                'state_event_id' => $event->id,
                'item_code'      => $data['item_code'] ?? $participant->registration->item_code,
                'score'          => $data['score'] ?? null,
                'grade'          => $data['grade'] ?? null,
                'notes'          => $data['notes'] ?? null,
            ],
        );

        $this->syncAggregatedMark($event, $data['item_id'], (int) $data['participant_id']);

        return ['message' => 'Judge score saved.'];
    }

    public function syncAggregatedMark(StateFestEvent $event, string $itemId, int $participantId): void
    {
        $judgeIds = StateJudgeAssignment::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->pluck('user_id');

        if ($judgeIds->isEmpty()) {
            return;
        }

        $scores = StateJudgeScore::where('state_event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('participant_id', $participantId)
            ->whereIn('judge_user_id', $judgeIds)
            ->whereNotNull('score')
            ->get();

        // Not every assigned judge has scored yet — wait rather than publish a partial
        // average, same rule the tenant-level system uses.
        if ($scores->count() < $judgeIds->count()) {
            return;
        }

        $avgScore = round((float) $scores->avg('score'), 2);
        $grade = $this->gradePoints->resolveGradeFromScore($event, $avgScore);

        $participant = StateFestParticipant::with('registration')->find($participantId);

        StateFestMark::updateOrCreate(
            ['registration_id' => $participant?->registration_id],
            [
                'state_event_id'  => $event->id,
                'registration_id' => $participant?->registration_id,
                'participant_id'  => $participantId,
                'score'           => $avgScore,
                'grade'           => $grade,
                'status'          => 'draft',
            ],
        );
    }

    /** @return array<int, StateJudgeScore> keyed by participant_id */
    public function scoresForJudge(StateFestEvent $event, int $judgeUserId, ?array $itemIds = null): array
    {
        return StateJudgeScore::where('state_event_id', $event->id)
            ->where('judge_user_id', $judgeUserId)
            ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds ?: ['00000000-0000-0000-0000-000000000000']))
            ->get()
            ->keyBy('participant_id')
            ->all();
    }
}
