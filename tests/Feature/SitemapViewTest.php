<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The sitemap template starts with an XML declaration. Written literally, "<?xml" is parsed
 * as PHP when the server has short_open_tag=On and the compiled view dies with "syntax
 * error, unexpected identifier 'version'". It has to reach the browser as echoed output.
 */
class SitemapViewTest extends TestCase
{
    use RefreshDatabase;
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

    public function test_sitemap_endpoint_returns_xml_response(): void
    {
        $tenant = \App\Models\Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'test-seo.example.com',
            'is_active' => true,
        ]);

        $response = $this->get('http://test-seo.example.com/sitemap.xml');
        $response->assertStatus(200);
        $this->assertStringContainsString('application/xml', $response->headers->get('content-type'));
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $response->getContent());
    }
}
