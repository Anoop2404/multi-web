<?php

namespace Tests\Unit\Support;

use App\Support\FestItemCatalog;
use Tests\TestCase;

class FestItemCatalogSportsTest extends TestCase
{
    public function test_sports_catalog_has_no_open_age_items(): void
    {
        $items = FestItemCatalog::sportsItems();

        $openAge = collect($items)->filter(fn (array $row) => ($row['age_group'] ?? '') === 'open');

        $this->assertSame(0, $openAge->count(), 'Open-age sports items should be expanded to U14/U17/U19.');
    }

    public function test_open_team_game_expands_to_age_and_gender_variants(): void
    {
        $items = FestItemCatalog::sportsItems();

        $footballU14Boys = collect($items)->first(
            fn (array $row) => $row['title'] === 'U14 — Football — Boys Team'
                && ($row['age_group'] ?? '') === 'u14'
                && ($row['gender'] ?? '') === 'male'
        );

        $this->assertNotNull($footballU14Boys);

        $throwBallU17Girls = collect($items)->first(
            fn (array $row) => $row['title'] === 'U17 — Throw Ball — Girls Team'
        );

        $this->assertNotNull($throwBallU17Girls);
    }

    public function test_mixed_board_game_expands_to_boys_and_girls_per_age(): void
    {
        $items = FestItemCatalog::sportsItems();

        $chessU14Boys = collect($items)->first(
            fn (array $row) => str_contains($row['title'], 'U14 — Chess — Individual')
                && str_contains($row['title'], 'Boys')
        );

        $chessU14Girls = collect($items)->first(
            fn (array $row) => str_contains($row['title'], 'U14 — Chess — Individual')
                && str_contains($row['title'], 'Girls')
        );

        $this->assertNotNull($chessU14Boys);
        $this->assertNotNull($chessU14Girls);
    }

    public function test_mixed_doubles_keeps_mixed_gender_with_age_split(): void
    {
        $items = FestItemCatalog::sportsItems();

        $mixed = collect($items)->first(
            fn (array $row) => $row['title'] === 'U14 — Badminton — Mixed Doubles'
                && ($row['gender'] ?? '') === 'mixed'
        );

        $this->assertNotNull($mixed);
    }

    public function test_newly_added_sports_have_age_splits(): void
    {
        $items = FestItemCatalog::sportsItems();

        $this->assertNotNull(collect($items)->first(fn (array $row) => $row['title'] === 'U17 — Netball — Girls Team'));
        $this->assertNotNull(collect($items)->first(fn (array $row) => $row['title'] === 'U14 — Sepak Takraw — Boys Team'));
        $this->assertNotNull(collect($items)->first(fn (array $row) => $row['title'] === 'U19 — Skating — Girls'));
        $this->assertNotNull(collect($items)->first(fn (array $row) => $row['title'] === 'U14 — Wrestling — Girls (Weight Category)'));
    }
}
