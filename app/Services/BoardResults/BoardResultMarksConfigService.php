<?php

namespace App\Services\BoardResults;

use App\Models\BoardResultMarksConfig;
use Illuminate\Support\Collection;

/**
 * Resolves the admin-locked "out of" (total) marks for a class (10) or class + stream (12).
 * Schools no longer type this in — see BoardResultController::createToppersFromRows() etc.
 */
class BoardResultMarksConfigService
{
    /**
     * When $academicYear is null (every pre-existing call site), only the global
     * (academic_year IS NULL) row is eligible — unchanged from before the column existed.
     * When a year is passed, a row explicit to that year is preferred, falling back to the
     * global row if no year-specific override exists.
     */
    public function resolve(string $sahodayaId, int $class, ?int $streamId = null, ?string $academicYear = null): int
    {
        $query = BoardResultMarksConfig::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('class', $class)
            ->where('stream_id', $streamId);

        if ($academicYear !== null) {
            $query->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->orWhereNull('academic_year');
            });
        } else {
            $query->whereNull('academic_year');
        }

        $config = $query->orderByRaw('academic_year is null')->first();

        return $config?->total_marks ?? BoardResultMarksConfig::DEFAULT_TOTAL_MARKS;
    }

    /** @return Collection<int, BoardResultMarksConfig> */
    public function allForSahodaya(string $sahodayaId): Collection
    {
        return BoardResultMarksConfig::query()->where('sahodaya_id', $sahodayaId)->get();
    }

    public function upsert(string $sahodayaId, int $class, ?int $streamId, int $totalMarks, ?string $academicYear = null): BoardResultMarksConfig
    {
        return BoardResultMarksConfig::updateOrCreate(
            ['sahodaya_id' => $sahodayaId, 'class' => $class, 'stream_id' => $streamId, 'academic_year' => $academicYear],
            ['total_marks' => $totalMarks],
        );
    }
}
