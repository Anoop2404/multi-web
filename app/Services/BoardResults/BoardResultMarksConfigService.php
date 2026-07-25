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
    public function resolve(string $sahodayaId, int $class, ?int $streamId = null): int
    {
        return BoardResultMarksConfig::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('class', $class)
            ->where('stream_id', $streamId)
            ->value('total_marks') ?? BoardResultMarksConfig::DEFAULT_TOTAL_MARKS;
    }

    /** @return Collection<int, BoardResultMarksConfig> */
    public function allForSahodaya(string $sahodayaId): Collection
    {
        return BoardResultMarksConfig::query()->where('sahodaya_id', $sahodayaId)->get();
    }

    public function upsert(string $sahodayaId, int $class, ?int $streamId, int $totalMarks): BoardResultMarksConfig
    {
        return BoardResultMarksConfig::updateOrCreate(
            ['sahodaya_id' => $sahodayaId, 'class' => $class, 'stream_id' => $streamId],
            ['total_marks' => $totalMarks],
        );
    }
}
