<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;

class FestMarkSaveService
{
    public function __construct(
        private FestGradePointService $gradePointService,
        private FestAthleticRecordService $recordService,
    ) {}

    /** @return array{message: string, record_break: bool} */
    public function save(FestEvent $event, array $data, int $lockedBy): array
    {
        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);

        if (! empty($data['score']) && empty($data['grade'])) {
            $data['grade'] = $this->gradePointService->resolveGradeFromScore(
                $event,
                $data['item_id'],
                (float) $data['score']
            );
        }

        $mark = FestMark::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            array_merge($data, [
                'event_id'  => $event->id,
                'locked_by' => $lockedBy,
                'locked_at' => now(),
            ])
        );

        if (empty($mark->score) && ($mark->grade || $mark->position)) {
            $mark->update(['score' => $this->gradePointService->pointsForMark($event, $mark->fresh())]);
        }

        $recordResult = $this->recordService->evaluateMark($mark->fresh());

        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());

        $message = 'Mark saved.';
        if ($recordResult['record_break']) {
            $message .= ' '.$recordResult['message'];
        }

        return [
            'message'      => $message,
            'record_break' => (bool) $recordResult['record_break'],
        ];
    }
}
