<?php

namespace Tests\Unit\Services;

use App\Services\Events\FestItemHeadService;
use Tests\TestCase;

class FestItemHeadServiceTest extends TestCase
{
    public function test_chess_items_resolve_to_chess_head(): void
    {
        $key = FestItemHeadService::resolveCatalogHeadKey([
            'title' => 'U14 — Chess — Individual (Swiss System) — Boys',
            'sport_discipline' => 'board_game',
        ]);

        $this->assertSame('chess', $key);
    }

    public function test_carrom_items_resolve_to_carrom_head(): void
    {
        $key = FestItemHeadService::resolveCatalogHeadKey([
            'title' => 'U17 — Carrom — Girls',
            'sport_discipline' => 'board_game',
        ]);

        $this->assertSame('carrom', $key);
    }

    public function test_track_items_resolve_to_athletics_head(): void
    {
        $key = FestItemHeadService::resolveCatalogHeadKey([
            'title' => 'U14 — 100m Boys',
            'sport_discipline' => 'track',
        ]);

        $this->assertSame('athletics', $key);
    }
}
