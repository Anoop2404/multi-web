<?php

namespace Tests\Unit\Support;

use App\Support\IdCardDiePreset;
use PHPUnit\Framework\TestCase;

class IdCardDiePresetTest extends TestCase
{
    public function test_badge_die_matches_the_measured_ten_card_sheet(): void
    {
        $preset = collect(IdCardDiePreset::options())->firstWhere('key', 'badge-die-10-up-19x13');

        $this->assertNotNull($preset);
        $this->assertSame(90, $preset['card_width_mm']);
        $this->assertSame(140, $preset['card_height_mm']);
        $this->assertSame(10, $preset['cards_per_page']);
        $this->assertSame(482.6, $preset['page_width_mm']);
        $this->assertSame(330.2, $preset['page_height_mm']);
        $this->assertSame([
            'cols' => 5,
            'rows' => 2,
            'first_col_center_mm' => 61.3,
            'first_row_center_mm' => 95.1,
            'col_pitch_mm' => 90,
            'row_pitch_mm' => 140,
        ], $preset['grid']);

        $lastCardRight = $preset['grid']['first_col_center_mm']
            + (($preset['grid']['cols'] - 1) * $preset['grid']['col_pitch_mm'])
            + ($preset['card_width_mm'] / 2);
        $lastCardBottom = $preset['grid']['first_row_center_mm']
            + (($preset['grid']['rows'] - 1) * $preset['grid']['row_pitch_mm'])
            + ($preset['card_height_mm'] / 2);

        $this->assertEqualsWithDelta(466.3, $lastCardRight, 0.0001);
        $this->assertEqualsWithDelta(305.1, $lastCardBottom, 0.0001);
    }
}
