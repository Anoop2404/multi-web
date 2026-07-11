<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use Illuminate\Validation\ValidationException;

class TopperCountService
{
    public const DEFAULT_TOP_N = 5;

    public function resolveCap(string $sahodayaId, int $class, string $scope = TopperCountConfig::SCOPE_OVERALL, ?int $streamId = null): int
    {
        $query = TopperCountConfig::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('scope', $scope)
            ->where(function ($q) use ($class) {
                $q->where('class', $class)->orWhereNull('class');
            });

        if ($scope === TopperCountConfig::SCOPE_STREAM && $streamId) {
            $query->where(function ($q) use ($streamId) {
                $q->where('stream_id', $streamId)->orWhereNull('stream_id');
            });
        }

        $config = $query
            ->orderByRaw('class is null') // prefer class-specific
            ->orderByRaw('stream_id is null')
            ->first();

        return max(1, (int) ($config?->top_n ?? self::DEFAULT_TOP_N));
    }

    public function assertCanAdd(BoardResult $boardResult, string $sahodayaId, ?Topper $updating = null): void
    {
        $cap = $this->resolveCap($sahodayaId, (int) $boardResult->class);
        $count = Topper::query()
            ->where('board_result_id', $boardResult->id)
            ->when($updating, fn ($q) => $q->where('id', '!=', $updating->id))
            ->count();

        if ($count >= $cap) {
            throw ValidationException::withMessages([
                'rank' => "Topper limit reached ({$cap}). Adjust Sahodaya Top-N settings or remove an existing topper.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TopperCountConfig
     */
    public function upsert(string $sahodayaId, array $data): TopperCountConfig
    {
        $keys = [
            'sahodaya_id' => $sahodayaId,
            'class' => $data['class'] ?? null,
            'scope' => $data['scope'] ?? TopperCountConfig::SCOPE_OVERALL,
            'stream_id' => $data['stream_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
        ];

        return TopperCountConfig::updateOrCreate($keys, [
            'top_n' => max(1, min(50, (int) ($data['top_n'] ?? self::DEFAULT_TOP_N))),
        ]);
    }
}
