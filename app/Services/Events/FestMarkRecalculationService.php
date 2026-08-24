<?php

namespace App\Services\Events;

use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestMark;

/**
 * Editing the Rank Points table or Grade Master bands mid-event doesn't retroactively
 * touch marks already saved — Results and championship totals already recompute live
 * against current config on every render, but FestMark.grade/score themselves (what
 * Mark Entry reads directly) stay frozen at whatever they were computed as on their
 * last save. This re-runs that same derivation for every mark in the event so an
 * admin can explicitly refresh them after changing either config, instead of the two
 * only ever falling back into sync one mark at a time as each happens to get re-saved.
 */
class FestMarkRecalculationService
{
    public function __construct(private FestGradePointService $gradePointService) {}

    /** @return array{marks_checked: int, marks_updated: int} */
    public function recalculateEvent(FestEvent $event): array
    {
        $eventIds = $event->reportableEventIds();

        $marks = FestMark::whereIn('event_id', $eventIds)->with('item')->get();

        $checked = 0;
        $updated = 0;

        foreach ($marks as $mark) {
            $checked++;
            $item = $mark->item;
            if (! $item) {
                continue;
            }

            $dirty = false;

            if ($item->total_marks !== null) {
                // total_marks set → this item grades by raw score/percentage, so
                // FestMark.score is the judge's actual entry — never overwrite it, only
                // refresh the grade it resolves to under the current Grade Master bands.
                if ($mark->score !== null) {
                    $newGrade = $this->gradePointService->resolveGradeFromScore($event, $item->id, (float) $mark->score);
                    if ($newGrade !== $mark->grade) {
                        $mark->grade = $newGrade;
                        $dirty = true;
                    }
                }
            } elseif ($mark->grade || $mark->position) {
                // No total_marks → FestMark.score holds derived championship points
                // (from rank position or grade), not a raw entry — safe to refresh
                // unconditionally against the current Rank Points / Grade Points
                // Master config, the same computation Results already shows live.
                $newScore = $this->gradePointService->pointsForMark($event, $mark);
                if ((int) $newScore !== (int) ($mark->score ?? 0)) {
                    $mark->score = $newScore;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $mark->save();
                $updated++;
            }
        }

        if ($updated > 0) {
            EventContext::for($event)->recalculateSchoolPoints();
            FestScoreboardUpdated::dispatch($event->fresh());
        }

        return ['marks_checked' => $checked, 'marks_updated' => $updated];
    }
}
