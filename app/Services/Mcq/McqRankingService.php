<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;

class McqRankingService
{
    public function rankExam(McqExam $exam): int
    {
        $marks = McqMark::query()
            ->whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))
            ->with('registration')
            ->get()
            ->sortByDesc(fn (McqMark $m) => [(float) $m->score, (int) $m->correct_count])
            ->values();

        $rank = 0;
        $last = null;
        $position = 0;

        foreach ($marks as $mark) {
            $position++;
            $key = [(float) $mark->score, (int) $mark->correct_count];
            if ($last !== $key) {
                $rank = $position;
                $last = $key;
            }
            $mark->update(['rank' => $rank]);
        }

        return $marks->count();
    }
}
