<?php

namespace Tests\Unit\Services\BoardResults;

use App\Models\BoardResult;
use App\Services\BoardResults\RankingEngine;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class RankingEngineTest extends TestCase
{
    public function test_pass_percent_ranking_uses_competition_ties(): void
    {
        $engine = new RankingEngine;
        $results = collect([
            $this->fakeResult(1, 's1', 98.5, 10, 100),
            $this->fakeResult(2, 's2', 98.5, 8, 90),
            $this->fakeResult(3, 's3', 95.0, 5, 80),
        ]);

        $ranked = $engine->rankByPassPercent($results);

        $this->assertSame('s1', $ranked[0]['entity_id']); // more distinctions orders first within tie
        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame('s2', $ranked[1]['entity_id']);
        $this->assertSame(1, $ranked[1]['rank']); // equal pass% shares rank
        $this->assertSame('pass_percent_then_distinctions', $ranked[1]['tie_rule_applied']);
        $this->assertSame('s3', $ranked[2]['entity_id']);
        $this->assertSame(3, $ranked[2]['rank']);
    }

    public function test_overall_ranking_prefers_highest_mark(): void
    {
        $engine = new RankingEngine;
        $results = collect([
            $this->fakeResult(1, 'a', 99.0, 1, 50, 95.0),
            $this->fakeResult(2, 'b', 90.0, 20, 50, 99.5),
            $this->fakeResult(3, 'c', 90.0, 20, 50, 99.5),
        ]);

        $ranked = $engine->rankOverall($results);

        $this->assertSame('b', $ranked[0]['entity_id']);
        $this->assertSame(1, $ranked[0]['rank']);
        $this->assertSame(1, $ranked[1]['rank']); // exact score tie shares rank
        $this->assertSame('highest_mark_then_pass_percent', $ranked[1]['tie_rule_applied']);
        $this->assertSame(3, $ranked[2]['rank']); // competition ranking skips 2
        $this->assertSame('a', $ranked[2]['entity_id']);
    }

    private function fakeResult(
        int $id,
        string $tenantId,
        float $passPercent,
        int $distinctions,
        int $appeared,
        ?float $highestMark = null,
    ): BoardResult {
        $r = new BoardResult;
        $r->id = $id;
        $r->tenant_id = $tenantId;
        $r->class = 10;
        $r->examination_type = BoardResult::EXAM_AISSE;
        $r->pass_percent = $passPercent;
        $r->distinctions = $distinctions;
        $r->total_appeared = $appeared;
        $r->highest_mark = $highestMark;

        return $r;
    }
}
