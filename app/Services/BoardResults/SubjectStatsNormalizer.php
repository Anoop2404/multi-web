<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Support\BoardExamSubjects;
use Illuminate\Support\Facades\Schema;

/**
 * Wire dead subject_stats column from topper subject marks (#154).
 */
class SubjectStatsNormalizer
{
    /**
     * @return array<string, array{top_score: float, topper_name: string, toppers_count: int}>
     */
    public function rebuild(BoardResult $boardResult): array
    {
        $boardResult->loadMissing('toppers');
        $stats = [];

        if (Schema::hasTable('topper_subject_marks')) {
            $topperIds = $boardResult->toppers->pluck('id')->all();
            $rows = TopperSubjectMark::query()
                ->whereIn('topper_id', $topperIds)
                ->with('topper')
                ->get();

            foreach ($rows as $row) {
                $subject = $row->subject_label;
                $mark = (float) $row->marks;
                $name = $row->topper?->name ?? '—';
                if (! isset($stats[$subject])) {
                    $stats[$subject] = [
                        'top_score' => $mark,
                        'topper_name' => $name,
                        'toppers_count' => 1,
                    ];
                    continue;
                }
                $stats[$subject]['toppers_count']++;
                if ($mark > $stats[$subject]['top_score']) {
                    $stats[$subject]['top_score'] = $mark;
                    $stats[$subject]['topper_name'] = $name;
                }
            }
        }

        if ($stats === []) {
            foreach ($boardResult->toppers as $topper) {
                if (! $topper instanceof Topper) {
                    continue;
                }
                foreach (BoardExamSubjects::normalizeSubjectMarks($topper->subject_marks ?? []) as $subject => $mark) {
                    if (! isset($stats[$subject])) {
                        $stats[$subject] = [
                            'top_score' => (float) $mark,
                            'topper_name' => $topper->name,
                            'toppers_count' => 1,
                        ];
                        continue;
                    }
                    $stats[$subject]['toppers_count']++;
                    if ($mark > $stats[$subject]['top_score']) {
                        $stats[$subject]['top_score'] = (float) $mark;
                        $stats[$subject]['topper_name'] = $topper->name;
                    }
                }
            }
        }

        ksort($stats);
        $boardResult->update(['subject_stats' => $stats === [] ? null : $stats]);

        return $stats;
    }
}
