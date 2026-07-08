<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use Illuminate\Validation\ValidationException;

class FestMarkSaveService
{
    public function __construct(
        private FestGradePointService $gradePointService,
        private FestAthleticRecordService $recordService,
    ) {}

    /** @return array{message: string, record_break: bool} */
    public function save(FestEvent $event, array $data, int $lockedBy): array
    {
        $participant = FestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);
        abort_if($participant->registration->status !== 'approved', 422, 'Marks can only be entered for approved registrations.');
        abort_if($participant->participant_role === 'standby', 422, 'Standby participants cannot receive marks.');
        abort_if($participant->disqualified_at !== null, 422, 'Disqualified participants cannot receive marks.');

        if ($event->event_type === 'sports') {
            $attendance = FestAttendance::query()
                ->where('event_id', $event->id)
                ->where('item_id', $data['item_id'])
                ->where('participant_id', $data['participant_id'])
                ->first();

            if ($attendance?->status === 'absent') {
                $hasMarkData = ! empty($data['position'])
                    || ! empty($data['score'])
                    || ! empty($data['measurement_value']);

                if ($hasMarkData) {
                    throw ValidationException::withMessages([
                        'position' => 'Cannot enter marks for an absent participant. Mark them present first.',
                    ]);
                }
            }
        }

        if (! empty($data['score']) && empty($data['grade'])) {
            $data['grade'] = $this->gradePointService->resolveGradeFromScore(
                $event,
                $data['item_id'],
                (float) $data['score']
            );
        }

        if ($event->event_type === 'sports' && ! empty($data['position']) && ($data['score'] ?? '') === '') {
            $item = \App\Models\FestEventItem::find($data['item_id']);
            $isGroup = in_array($item?->participant_type, ['group', 'team'], true);
            $data['score'] = app(FestRankPointService::class)->pointsForRank($event, (int) $data['position'], $isGroup);
        }

        $mark = FestMark::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            array_merge($data, [
                'event_id'  => $event->id,
                'locked_by' => $lockedBy,
                'locked_at' => now(),
            ])
        );

        if (($mark->score ?? '') === '' && ($mark->grade || $mark->position)) {
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
