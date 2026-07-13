<?php

namespace Tests\Unit\Support;

use App\Support\FestReportCatalog;
use Tests\TestCase;

class FestReportCatalogAreaPagesTest extends TestCase
{
    public function test_non_sports_includes_area_wise_and_excludes_house(): void
    {
        $pages = collect(FestReportCatalog::interactivePages('tenant-1', 9, 'custom'));
        $ids = $pages->pluck('id')->all();

        $this->assertContains('area-wise-participants', $ids);
        $this->assertContains('item-wise', $ids);
        $this->assertNotContains('house-detailed', $ids);
    }

    public function test_sports_excludes_area_wise(): void
    {
        $pages = collect(FestReportCatalog::interactivePages('tenant-1', 9, 'sports'));
        $ids = $pages->pluck('id')->all();

        $this->assertNotContains('area-wise-participants', $ids);
        $this->assertContains('head-wise-participants', $ids);
    }

    public function test_area_export_maps_to_preview_page(): void
    {
        $this->assertSame(
            'area-wise-participants',
            FestReportCatalog::previewPageForExport('area-wise-participants')
        );
    }
}
