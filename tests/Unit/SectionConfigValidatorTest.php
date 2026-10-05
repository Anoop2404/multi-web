<?php

namespace Tests\Unit;

use App\Support\SectionConfigValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SectionConfigValidatorTest extends TestCase
{
    // ── Phase 2: type validation ─────────────────────────────────────────────

    /** @test */
    public function it_rejects_an_invalid_email()
    {
        $this->expectException(ValidationException::class);
        SectionConfigValidator::validate('hero', 'centered', [
            'heading' => 'Fine',
            'cta_url' => 'not-a-url',
        ]);
    }

    /** @test */
    public function it_rejects_an_invalid_date()
    {
        $this->expectException(ValidationException::class);
        SectionConfigValidator::validate('some_section', 'with_date', [
            'event_date' => '31/02/2025',
        ]);
    }

    /** @test */
    public function it_rejects_an_end_date_before_start_date()
    {
        $this->expectException(ValidationException::class);
        SectionConfigValidator::validate('some_section', 'with_date_range', [
            'range' => ['from' => '2025-12-31', 'to' => '2025-01-01'],
        ]);
    }

    /** @test */
    public function it_accepts_a_valid_date_range()
    {
        $out = SectionConfigValidator::validate('some_section', 'with_date_range', [
            'range' => ['from' => '2025-01-01', 'to' => '2025-12-31'],
        ]);
        $this->assertEquals('2025-01-01', $out['range']['from']);
        $this->assertEquals('2025-12-31', $out['range']['to']);
    }

    /** @test */
    public function it_rejects_a_number_out_of_range()
    {
        $this->expectException(ValidationException::class);
        SectionConfigValidator::validate('hero', 'video-bg', [
            'heading' => 'Fine',
            'overlay_opacity' => 150,
        ]);
    }

    /** @test */
    public function it_accepts_a_number_within_range()
    {
        $out = SectionConfigValidator::validate('hero', 'video-bg', [
            'heading' => 'Fine',
            'overlay_opacity' => 50,
        ]);
        $this->assertEquals('50', $out['overlay_opacity']);
    }

    // ── Phase 3: repeater item metadata validation ───────────────────────────

    /** @test */
    public function it_validates_repeater_item_metadata_fields()
    {
        $out = SectionConfigValidator::validate('hero', 'with-quicklinks', [
            'heading' => 'Fine',
            'links' => [
                [
                    'label' => 'Home',
                    'url' => 'https://example.com',
                    'icon' => '🏠',
                    '_enabled' => true,
                    '_featured' => false,
                    '_start_date' => '2025-01-01',
                    '_end_date' => '2025-12-31',
                ],
            ],
        ]);
        $this->assertCount(1, $out['links']);
        $this->assertTrue($out['links'][0]['_enabled']);
    }

    /** @test */
    public function it_rejects_repeater_items_with_invalid_dates_in_metadata()
    {
        $this->expectException(ValidationException::class);
        SectionConfigValidator::validate('hero', 'with-quicklinks', [
            'heading' => 'Fine',
            'links' => [
                [
                    'label' => 'Home',
                    'url' => 'https://example.com',
                    '_end_date' => 'bad-date',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_passes_through_unknown_section_types()
    {
        $out = SectionConfigValidator::validate('unknown_type', 'unknown_variant', ['anything' => 'goes']);
        $this->assertEquals(['anything' => 'goes'], $out);
    }
}
