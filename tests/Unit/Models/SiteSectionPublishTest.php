<?php

namespace Tests\Unit\Models;

use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteSectionPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('site_sections', 'published_config')) {
            $this->markTestSkipped('FRD-20 site_sections columns not migrated.');
        }
    }

    public function test_publish_copies_draft_config_to_published(): void
    {
        $section = SiteSection::create([
            'tenant_id' => (string) Str::uuid(),
            'section_type' => 'hero',
            'variant' => 'full-bleed',
            'display_order' => 1,
            'is_active' => true,
            'status' => SiteSection::STATUS_DRAFT,
            'config' => ['heading' => 'Draft title'],
        ]);

        $section->publish();

        $this->assertSame(SiteSection::STATUS_PUBLISHED, $section->fresh()->status);
        $this->assertSame(['heading' => 'Draft title'], $section->fresh()->published_config);
        $this->assertSame(['heading' => 'Draft title'], $section->fresh()->publicConfig());
    }

    public function test_public_config_prefers_published_snapshot(): void
    {
        $section = new SiteSection([
            'config' => ['heading' => 'Draft'],
            'published_config' => ['heading' => 'Live'],
            'status' => SiteSection::STATUS_DRAFT,
        ]);

        $this->assertSame(['heading' => 'Live'], $section->publicConfig());
        $this->assertTrue($section->hasUnpublishedChanges());
    }
}
