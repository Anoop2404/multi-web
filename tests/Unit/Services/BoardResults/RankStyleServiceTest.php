<?php

namespace Tests\Unit\Services\BoardResults;

use App\Services\BoardResults\RankStyleService;
use PHPUnit\Framework\TestCase;

class RankStyleServiceTest extends TestCase
{
    public function test_competition_ranking_skips_after_ties(): void
    {
        $service = new RankStyleService();

        $rows = $service->assign([
            ['score' => 100],
            ['score' => 99],
            ['score' => 99],
            ['score' => 99],
            ['score' => 98],
        ], RankStyleService::STYLE_COMPETITION, fn (array $row) => $row['score']);

        $this->assertSame([1, 2, 2, 2, 5], array_column($rows, 'rank'));
    }

    public function test_dense_ranking_compacts_tied_groups(): void
    {
        $service = new RankStyleService();

        $rows = $service->assign([
            ['score' => 100],
            ['score' => 99],
            ['score' => 99],
            ['score' => 99],
            ['score' => 98],
        ], RankStyleService::STYLE_DENSE, fn (array $row) => $row['score']);

        $this->assertSame([1, 2, 2, 2, 3], array_column($rows, 'rank'));
    }

    public function test_sequential_ranking_assigns_unique_positions(): void
    {
        $service = new RankStyleService();

        $rows = $service->assign([
            ['score' => 100],
            ['score' => 99],
            ['score' => 99],
            ['score' => 99],
            ['score' => 98],
        ], RankStyleService::STYLE_SEQUENTIAL, fn (array $row) => $row['score']);

        $this->assertSame([1, 2, 3, 4, 5], array_column($rows, 'rank'));
    }
}
