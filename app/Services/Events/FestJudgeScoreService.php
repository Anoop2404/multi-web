<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestJudgeAssignment;
use App\Models\FestJudgeScore;
use App\Models\FestMark;
use App\Models\FestParticipant;

class FestJudgeScoreService
{
    public function __construct(
        private FestGradePointService $gradePointService,
        private FestAthleticRecordService $recordService,
    ) {}

    /** @return array{message: string} */
    public function save(FestEvent $event, array $data, int $judgeUserId): array
    {
        $participant = FestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);
        abort_if($participant->registration->status !== 'approved', 422, 'Scores can only be entered for approved registrations.');
        abort_if($participant->participant_role === 'standby', 422, 'Standby participants cannot receive scores.');
        abort_if($participant->disqualified_at !== null, 422, 'Disqualified participants cannot receive scores.');

        $assigned = FestJudgeAssignment::query()
            ->where('event_id', $event->id)
            ->where('item_id', $data['item_id'])
            ->where('user_id', $judgeUserId)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned as a judge for this item.');

        if (! empty($data['score']) && empty($data['grade'])) {
            $data['grade'] = $this->gradePointService->resolveGradeFromScore(
                $event,
                $data['item_id'],
                (float) $data['score'],
            );
        }

        FestJudgeScore::updateOrCreate(
            [
                'item_id'        => $data['item_id'],
                'participant_id' => $data['participant_id'],
                'judge_user_id'  => $judgeUserId,
            ],
            [
                'event_id'          => $event->id,
                'grade'             => $data['grade'] ?? null,
                'score'             => $data['score'] ?? null,
                'measurement_value' => $data['measurement_value'] ?? null,
                'measurement_unit'  => $data['measurement_unit'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ],
        );

        $this->syncAggregatedMark($event, (int) $data['item_id'], (int) $data['participant_id']);

        return ['message' => 'Judge score saved.'];
    }

    public function syncAggregatedMark(FestEvent $event, int $itemId, int $participantId): void
    {
        $judgeIds = FestJudgeAssignment::query()
            ->where('event_id', $event->id)
            ->where('item_id', $itemId)
            ->pluck('user_id');

        if ($judgeIds->isEmpty()) {
            return;
        }

        $scores = FestJudgeScore::query()
            ->where('event_id', $event->id)
            ->where('item_id', $itemId)
            ->where('participant_id', $participantId)
            ->whereIn('judge_user_id', $judgeIds)
            ->whereNotNull('score')
            ->get();

        if ($scores->count() < $judgeIds->count()) {
            return;
        }

        $avgScore = round($scores->avg('score'), 2);
        $grade = $this->gradePointService->resolveGradeFromScore($event, $itemId, $avgScore);

        $mark = FestMark::updateOrCreate(
            ['item_id' => $itemId, 'participant_id' => $participantId],
            [
                'event_id' => $event->id,
                'score'    => $avgScore,
                'grade'    => $grade,
            ],
        );

        if (empty($mark->score) && ($mark->grade || $mark->position)) {
            $mark->update(['score' => $this->gradePointService->pointsForMark($event, $mark->fresh())]);
        }

        $this->recordService->evaluateMark($mark->fresh());
        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());
    }

    /** @return array<int, FestJudgeScore> keyed by participant_id */
    public function scoresForJudge(FestEvent $event, int $judgeUserId, ?array $itemIds = null): array
    {
        return FestJudgeScore::query()
            ->where('event_id', $event->id)
            ->where('judge_user_id', $judgeUserId)
            ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds ?: [0]))
            ->get()
            ->keyBy('participant_id')
            ->all();
    }
}
