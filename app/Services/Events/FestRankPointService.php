<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRankPoint;
use Illuminate\Support\Collection;

class FestRankPointService
{
    /** Standard school athletics: rank → championship points. */
    public const ATHLETICS_STANDARD = [
        1 => 8,
        2 => 7,
        3 => 6,
        4 => 5,
        5 => 4,
        6 => 3,
    ];

    public function forEvent(FestEvent $event, bool $isGroup = false): Collection
    {
        return FestRankPoint::query()
            ->where('event_id', $event->id)
            ->where('is_group', $isGroup)
            ->orderBy('rank')
            ->get();
    }

    /** @return list<array{rank: int, points: int, is_group: bool}> */
    public function listForEvent(FestEvent $event, bool $isGroup = false): array
    {
        $rows = $this->forEvent($event, $isGroup);

        if ($rows->isEmpty() && $event->event_type === 'sports' && ! $isGroup) {
            return collect(self::ATHLETICS_STANDARD)
                ->map(fn (int $points, int $rank) => [
                    'rank'     => $rank,
                    'points'   => $points,
                    'is_group' => false,
                ])
                ->values()
                ->all();
        }

        return $rows->map(fn (FestRankPoint $row) => [
            'rank'     => (int) $row->rank,
            'points'   => (int) $row->points,
            'is_group' => (bool) $row->is_group,
        ])->values()->all();
    }

    public function pointsForRank(FestEvent $event, int $rank, bool $isGroup = false): int
    {
        if ($rank < 1) {
            return 0;
        }

        $configured = FestRankPoint::query()
            ->where('event_id', $event->id)
            ->where('rank', $rank)
            ->where('is_group', $isGroup)
            ->value('points');

        if ($configured !== null) {
            return (int) $configured;
        }

        if ($event->event_type === 'sports' && ! $isGroup) {
            return (int) (self::ATHLETICS_STANDARD[$rank] ?? 0);
        }

        return 0;
    }

    /** @param list<array{rank: int|string, points: int|string, is_group?: bool}> $rows */
    public function replaceForEvent(FestEvent $event, array $rows, bool $isGroup = false): int
    {
        FestRankPoint::query()
            ->where('event_id', $event->id)
            ->where('is_group', $isGroup)
            ->delete();

        $saved = 0;

        foreach ($rows as $row) {
            $rank = (int) ($row['rank'] ?? 0);
            if ($rank < 1) {
                continue;
            }

            $points = $row['points'] ?? null;
            if ($points === null || $points === '') {
                continue;
            }

            FestRankPoint::create([
                'event_id' => $event->id,
                'rank'     => $rank,
                'points'   => max(0, (int) $points),
                'is_group' => (bool) ($row['is_group'] ?? $isGroup),
            ]);
            $saved++;
        }

        return $saved;
    }

    public function seedAthleticsStandard(FestEvent $event, bool $isGroup = false): int
    {
        $rows = collect(self::ATHLETICS_STANDARD)
            ->map(fn (int $points, int $rank) => [
                'rank'     => $rank,
                'points'   => $points,
                'is_group' => $isGroup,
            ])
            ->values()
            ->all();

        return $this->replaceForEvent($event, $rows, $isGroup);
    }
}
