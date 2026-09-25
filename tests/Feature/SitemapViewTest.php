<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The sitemap template starts with an XML declaration. Written literally, "<?xml" is parsed
 * as PHP when the server has short_open_tag=On and the compiled view dies with "syntax
 * error, unexpected identifier 'version'". It has to reach the browser as echoed output.
 */
class SitemapViewTest extends TestCase
{
    public function test_the_xml_declaration_is_echoed_and_first_in_the_output(): void
    {
        $source = file_get_contents(resource_path('views/public/sitemap.blade.php'));
        $compiled = Blade::compileString($source);

        // Any raw "<?xml" left in the compiled file would be read as PHP with short tags on.
        $this->assertStringNotContainsString("\n<?xml", "\n".$compiled);

        $html = view('public.sitemap', ['urls' => [['loc' => 'https://x.test/', 'lastmod' => '2026-01-01', 'priority' => '1.0', 'changefreq' => 'daily']]])->render();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $html);
        $this->assertStringContainsString('<loc>https://x.test/</loc>', $html);
    }
}
