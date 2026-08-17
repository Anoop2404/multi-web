<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;

class McqRankingService
{
    public function __construct(private McqGradeService $grades) {}

    public function rankExam(McqExam $exam): int
    {
        $bandsByLabel = collect($this->grades->bandsForExam($exam))
            ->keyBy(fn (array $band) => (string) $band['label']);

        $marks = McqMark::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('exam_id', $exam->id)
                ->whereNotIn('attendance_status', \App\Models\McqRegistration::BLOCKING_ATTENDANCE_STATUSES))
            ->with('registration')
            ->get();

        $eligibleIds = [];
        $eligible = $marks
            ->filter(function (McqMark $mark) use ($bandsByLabel, &$eligibleIds) {
                $grade = (string) ($mark->grade ?? '');
                $band = $bandsByLabel->get($grade);

                // Unknown grade: treat as rank-eligible so legacy data still ranks.
                $ok = $band === null || ! empty($band['rank_eligible']);
                if ($ok) {
                    $eligibleIds[$mark->id] = true;
                }

                return $ok;
            })
            ->sortByDesc(fn (McqMark $m) => [(float) $m->score, (int) $m->correct_count])
            ->values();

        foreach ($marks as $mark) {
            if (! isset($eligibleIds[$mark->id]) && $mark->rank !== null) {
                $mark->update(['rank' => null]);
            }
        }

        $rank = 0;
        $last = null;
        $position = 0;

        foreach ($eligible as $mark) {
            $position++;
            $key = [(float) $mark->score, (int) $mark->correct_count];
            if ($last !== $key) {
                $rank = $position;
                $last = $key;
            }
            $mark->update(['rank' => $rank]);
        }

        return $eligible->count();
    }

    /**
     * Re-rank the exam only once ranking is meaningful: when there is at
     * least one present registration and every present registration has a
     * mark recorded. Called from all mark-writing entry points so rankings
     * never go stale, without re-ranking on every keystroke mid-entry.
     *
     * Fix 2026-08-15: closes the "storeMark doesn't trigger re-ranking" gap
     * documented in mcq_exam_flow_audit_2026_08_13.md.
     */
    public function rankIfComplete(McqExam $exam): bool
    {
        $presentCount = \App\Models\McqRegistration::where('exam_id', $exam->id)
            ->where('attendance_status', 'present')
            ->count();

        $markedCount = McqMark::whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))->count();

        if ($presentCount > 0 && $markedCount >= $presentCount) {
            $this->rankExam($exam);

            return true;
        }

        return false;
    }
}
