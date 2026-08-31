<?php

namespace Tests\Unit\Support;

use App\Support\SahodayaWebsiteTemplateCatalog;
use Tests\TestCase;

class SahodayaWebsiteTemplateCatalogTest extends TestCase
{
    public function test_network_directory_and_premium_templates_validate(): void
    {
        $this->assertIsArray(SahodayaWebsiteTemplateCatalog::get('network-directory'));
        $this->assertIsArray(SahodayaWebsiteTemplateCatalog::get('sahodaya-premium'));
        $this->assertIsArray(SahodayaWebsiteTemplateCatalog::get('heritage-institutional'));
    }

    public function test_free_and_premium_templates_offer_the_same_section_types(): void
    {
        $free = collect(SahodayaWebsiteTemplateCatalog::get('network-directory')['sections'])
            ->pluck('section_type')->unique()->sort()->values()->all();

        $premium = collect(SahodayaWebsiteTemplateCatalog::get('sahodaya-premium')['sections'])
            ->pluck('section_type')->unique()->sort()->values()->all();

        $heritage = collect(SahodayaWebsiteTemplateCatalog::get('heritage-institutional')['sections'])
            ->pluck('section_type')->unique()->sort()->values()->all();

        $this->assertSame($free, $premium, 'Free and Premium templates must cover the same feature set — only design/variant should differ.');
        $this->assertSame($free, $heritage, 'Heritage Institutional must cover the same feature set as every other template — only design/variant should differ.');
    }

    public function test_all_catalog_entries_appear_in_summaries_with_a_key(): void
    {
        $keys = collect(SahodayaWebsiteTemplateCatalog::summaries())->pluck('key');

        $this->assertContains('network-directory', $keys);
        $this->assertContains('sahodaya-premium', $keys);
        $this->assertContains('heritage-institutional', $keys);
    }
}
