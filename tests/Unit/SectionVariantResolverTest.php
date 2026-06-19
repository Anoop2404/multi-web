<?php

namespace Tests\Unit;

use App\Support\SectionVariantResolver;
use PHPUnit\Framework\TestCase;

class SectionVariantResolverTest extends TestCase
{
    public function test_resolves_legacy_section_variant_aliases(): void
    {
        $this->assertSame('split-image', SectionVariantResolver::resolve('hero', 'split'));
        $this->assertSame('grid', SectionVariantResolver::resolve('news', 'card-grid'));
        $this->assertSame('photo-grid', SectionVariantResolver::resolve('staff', 'card-grid'));
    }

    public function test_resolves_sahodaya_downloads_path(): void
    {
        $this->assertSame(
            ['downloads_sahodaya', 'sahodaya-grid'],
            SectionVariantResolver::path('downloads', 'sahodaya-grid')
        );
    }

    public function test_resolves_nav_and_footer_variants_from_style_key(): void
    {
        $this->assertSame('logo-center', SectionVariantResolver::resolveNavVariant(['style' => 'logo-center']));
        $this->assertSame('three-column', SectionVariantResolver::resolveFooterVariant(['style' => 'dark']));
        $this->assertSame('minimal', SectionVariantResolver::resolveFooterVariant(['style' => 'light']));
    }
}
