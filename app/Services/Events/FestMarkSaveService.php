<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
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
        $item = FestEventItem::findOrFail($data['item_id']);
        abort_if($item->event_id !== $event->id, 403);

        $participant = FestParticipant::with('registration')->findOrFail($data['participant_id']);
        abort_if($participant->registration->event_id !== $event->id, 403);
        abort_if($participant->registration->item_id !== $item->id, 422, 'The participant is not registered for this item.');
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

        $existingMark = FestMark::where('item_id', $data['item_id'])
            ->where('participant_id', $data['participant_id'])
            ->first();

        // Only auto-derive grade from score when the caller isn't explicitly asserting
        // a grade of its own — comparing against what's already stored (rather than
        // just checking "is the incoming value blank") is what lets an admin clear a
        // grade back to null, or override it to something the score wouldn't produce,
        // even while a score is still on the row. Without this, a lingering score
        // silently re-derived the old grade on every save, so an explicit revert to
        // null never actually stuck.
        $gradeExplicitlyChanged = $existingMark && array_key_exists('grade', $data) && $data['grade'] !== $existingMark->grade;

        if (
            ! $gradeExplicitlyChanged
            && isset($data['score']) && $data['score'] !== null && $data['score'] !== ''
        ) {
            $data['grade'] = $this->gradePointService->resolveGradeFromScore(
                $event,
                (int) $data['item_id'],
                (float) $data['score']
            );
        }

        if ($event->event_type === 'sports' && ! empty($data['position']) && ($data['score'] ?? '') === '') {
            $data['score'] = app(FestRankPointService::class)->pointsForRank($event, (int) $data['position'], $item?->participant_type ?? 'individual');
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
