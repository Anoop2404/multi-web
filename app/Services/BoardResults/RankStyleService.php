<?php

namespace App\Services\BoardResults;

class RankStyleService
{
    public const STYLE_COMPETITION = 'competition';

    public const STYLE_DENSE = 'dense';

    public const STYLE_SEQUENTIAL = 'sequential';

    public const DEFAULT_STYLE = self::STYLE_COMPETITION;

    /**
     * @param  list<array<string, mixed>>  $rows  Sorted descending by score.
     * @param  callable(array<string, mixed>): float|int|null  $scoreGetter
     * @return list<array<string, mixed>>
     */
    public function assign(array $rows, string $style, callable $scoreGetter, string $rankKey = 'rank'): array
    {
        $style = $this->normalize($style);
        if ($rows === []) {
            return [];
        }

        $ranked = [];
        $lastScore = null;
        $denseRank = 0;

        foreach ($rows as $index => $row) {
            $score = $scoreGetter($row);
            $score = is_numeric($score) ? (float) $score : null;

            if ($style === self::STYLE_SEQUENTIAL) {
                $row[$rankKey] = $index + 1;
            } elseif ($style === self::STYLE_DENSE) {
                if ($lastScore === null || $this->differs($score, $lastScore)) {
                    $denseRank++;
                }
                $row[$rankKey] = max(1, $denseRank);
            } else {
                if ($lastScore === null || $this->differs($score, $lastScore)) {
                    $row[$rankKey] = $index + 1;
                } else {
                    $row[$rankKey] = $ranked[$index - 1][$rankKey];
                }
            }

            $ranked[] = $row;
            $lastScore = $score;
        }

        return $ranked;
    }

    public function normalize(?string $style): string
    {
        return in_array($style, [self::STYLE_COMPETITION, self::STYLE_DENSE, self::STYLE_SEQUENTIAL], true)
            ? $style
            : self::DEFAULT_STYLE;
    }

    private function differs(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }

        return abs($a - $b) > 0.0001;
    }
}
