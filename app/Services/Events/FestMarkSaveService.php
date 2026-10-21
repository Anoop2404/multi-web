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
        if ($event->level_round === 'school') {
            abort_if(in_array($participant->registration->status, ['withdrawn', 'rejected', 'cancelled'], true), 422, 'Cancelled or rejected registrations cannot receive marks.');
        } else {
            abort_if($participant->registration->status !== 'approved', 422, 'Marks can only be entered for approved registrations.');
        }
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

        // Per-item result mode (FestEventItem::result_method): the admin chooses on the
        // item itself whether the result is taken from a measurement (time/distance) or
        // from the rank itself (rank) — and this is the one place every save path
        // (admin mark entry, judge/coordinator portals, school rounds, CSV import)
        // converges, so the mode is enforced here rather than per-form.
        $resultMethod = strtolower((string) $item->result_method);
        if ($resultMethod === 'rank' && ! empty($data['measurement_value'])) {
            throw ValidationException::withMessages([
                'measurement_value' => 'This item is rank-only — its result is the rank from the judges\' table, so no time/distance measurement may be recorded for it.',
            ]);
        }

        // Measured items auto-rank from the recorded time/distance (FestSportsAutoRankService)
        // — saving a blank measurement on one (fresh, or overwriting a recorded one) would
        // silently drop that participant from the next ranking pass.
        if (in_array($resultMethod, ['time', 'distance'], true)) {
            $measurement = trim((string) (array_key_exists('measurement_value', $data)
                ? $data['measurement_value'] : $existingMark?->measurement_value));
            $position = array_key_exists('position', $data) ? $data['position'] : $existingMark?->position;
            $score = array_key_exists('score', $data) ? $data['score'] : $existingMark?->score;

            $isZeroOrBlank = $measurement === '' || $measurement === '0' || $measurement === '0.0' || $measurement === '0.00';

            if ($isZeroOrBlank && ($position || ($score !== null && $score !== '' && (float) $score > 0))) {
                // Rank entered by hand on a measured item requires a non-zero measurement.
                throw ValidationException::withMessages([
                    'measurement_value' => $resultMethod === 'time'
                        ? 'This item is time-based — record a valid time (e.g. 12.5 s or 1:02.5) before assigning a rank.'
                        : 'This item is distance-based — record a valid distance (e.g. 4.2 m) before assigning a rank.',
                ]);
            }

            if (! $isZeroOrBlank) {
                if ($resultMethod === 'time') {
                    $parsedTime = \App\Support\FestSportsMeasurementHelper::parseTimeInSeconds($measurement);
                    if ($parsedTime === null || $parsedTime <= 0) {
                        throw ValidationException::withMessages([
                            'measurement_value' => 'Invalid time format. Enter seconds (e.g. 12.45) or minutes:seconds (e.g. 1:02.50).',
                        ]);
                    }
                } elseif ($resultMethod === 'distance') {
                    $unit = $data['measurement_unit'] ?? $existingMark?->measurement_unit ?? 'm';
                    $parsedDist = \App\Support\FestSportsMeasurementHelper::parseDistanceInMeters($measurement, $unit);
                    if ($parsedDist === null || $parsedDist <= 0) {
                        throw ValidationException::withMessages([
                            'measurement_value' => 'Invalid distance format. Enter a valid distance (e.g. 5.45 or 12.30 m).',
                        ]);
                    }
                }
            }
        }

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

        $mark = FestMark::updateOrCreate(
            ['item_id' => $data['item_id'], 'participant_id' => $data['participant_id']],
            array_merge($data, [
                'event_id'  => $event->id,
                'locked_by' => $lockedBy,
                'locked_at' => now(),
            ])
        );

        if ($event->event_type !== 'sports' && ($mark->score ?? '') === '' && ($mark->grade || $mark->position)) {
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
