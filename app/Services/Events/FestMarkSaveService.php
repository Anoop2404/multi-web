<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Support\CertificateStalenessMarker;
use Illuminate\Validation\ValidationException;

class FestMarkSaveService
{
    public function __construct(
        private FestGradePointService $gradePointService,
        private FestAthleticRecordService $recordService,
    ) {}

    /**
     * @param  bool  $recalculate  Pass false when the caller is saving several marks for
     *                             one logical submit (team/group expansion in
     *                             FestMarkEntryController::store(), the CSV loop in
     *                             FestMarksImportController::importStore()) and will call
     *                             recalculate() itself once after the whole batch — see
     *                             that method's docblock for why this matters: leaving it
     *                             true inside a loop reran the event's ENTIRE points
     *                             recalculation once per participant (recalculateSchoolPoints()
     *                             rescans every FestMark row in the event, not just this
     *                             one), so a 10-member team save that already took N writes
     *                             also took N full-event recomputes — the dominant cost, not
     *                             the writes themselves. Every other caller (single-mark
     *                             coordinator/ops/school endpoints) keeps the default and is
     *                             unaffected.
     * @param  ?FestEventItem  $item  Pass the item when the caller already has it loaded
     *                                (e.g. bulkStore()'s per-batch item map) to skip the
     *                                findOrFail() below.
     * @param  ?FestParticipant  $participant  Same, for the participant — must already
     *                                have its `registration` relation loaded.
     * @return array{message: string, record_break: bool}
     */
    public function save(FestEvent $event, array $data, int $lockedBy, bool $recalculate = true, ?FestEventItem $item = null, ?FestParticipant $participant = null): array
    {
        $item ??= FestEventItem::findOrFail($data['item_id']);
        abort_if($item->event_id !== $event->id, 403);

        $participant ??= FestParticipant::with('registration')->findOrFail($data['participant_id']);
        $participant->loadMissing('registration');
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
            $derivedGrade = $this->gradePointService->resolveGradeFromScore(
                $event,
                (int) $data['item_id'],
                (float) $data['score'],
                $item
            );
            if ($derivedGrade !== null || empty($data['grade'])) {
                $data['grade'] = $derivedGrade;
            }
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

        // A certificate already rendered before this mark existed (or with a different
        // grade/position) would otherwise keep serving its cached PDF indefinitely —
        // cachedOrFreshPdf() only re-renders when is_stale is true, and nothing else on
        // this save path ever flips it (confirmed: CertificateStalenessMarker had zero
        // call sites anywhere before this one). markStaleForParticipant() covers this
        // participant's own winner certificate, if any; the aggregate call covers their
        // participation certificate, which is anchored to a different FestParticipant
        // row (generateParticipationForEvent()'s "first by id" anchor) and lists every
        // item + grade they have across the whole event, not just this one.
        CertificateStalenessMarker::markStaleForParticipant($participant->id);
        CertificateStalenessMarker::markStaleForParticipationAggregate($event->id, $participant->student_id, $participant->teacher_id);

        // evaluateMark() needs these two relations when record tracking is on for this
        // event; loadMissing() is a no-op when the caller already prefetched them (the
        // common bulk-save case) and only queries here for a single, interactive save().
        $participant->loadMissing(['student', 'registration.school']);
        $recordResult = $this->recordService->evaluateMark($mark->fresh(), $event, $item, $participant);

        if ($recalculate) {
            $this->recalculate($event);
        }

        $message = 'Mark saved.';
        if ($recordResult['record_break']) {
            $message .= ' '.$recordResult['message'];
        }

        return [
            'message'      => $message,
            'record_break' => (bool) $recordResult['record_break'],
        ];
    }

    /**
     * The whole-event points recalculation + scoreboard broadcast that a single save()
     * normally does inline. Call this once after saving a batch with recalculate: false
     * — see save()'s docblock.
     */
    public function recalculate(FestEvent $event): void
    {
        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());
    }
}
