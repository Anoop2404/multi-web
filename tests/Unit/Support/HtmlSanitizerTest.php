<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_tags_from_rich_html(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><strong>World</strong>';
        $clean = HtmlSanitizer::rich($html);

        $this->assertStringContainsString('Hello', $clean);
        $this->assertStringContainsString('World', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_blocks_javascript_href(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $clean = HtmlSanitizer::rich($html);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_blocks_obfuscated_and_unknown_link_protocols(): void
    {
        $html = '<a href="java&#x0A;script:alert(1)">Bad</a><a href="vbscript:msgbox(1)">Also bad</a><a href="/admissions">Good</a>';
        $clean = HtmlSanitizer::rich($html);

        $this->assertStringNotContainsString('script:', $clean);
        $this->assertStringContainsString('href="/admissions"', $clean);
    }

    public function test_keeps_supported_rich_text_formatting(): void
    {
        $html = '<h2>Welcome</h2><p>Read <strong>carefully</strong>.</p><ul><li>First</li></ul>';
        $clean = HtmlSanitizer::rich($html);

        $this->assertStringContainsString('<h2>Welcome</h2>', $clean);
        $this->assertStringContainsString('<strong>carefully</strong>', $clean);
        $this->assertStringContainsString('<ul><li>First</li></ul>', $clean);
    }

    public function test_rich_display_preserves_legacy_plain_text_line_breaks(): void
    {
        $rendered = HtmlSanitizer::richForDisplay("First line\nSecond line");

        $this->assertStringContainsString('First line<br', $rendered);
        $this->assertStringContainsString('Second line', $rendered);
    }

    public function test_section_config_uses_field_schema_to_clean_rich_editor_values(): void
    {
        $config = HtmlSanitizer::sanitizeSectionConfig([
            'left_content' => '<p onclick="bad()">Left</p>',
            'right_content' => '<a href="javascript:bad()">Right</a>',
        ], 'about', 'two-column');

        $this->assertStringNotContainsString('onclick', $config['left_content']);
        $this->assertStringNotContainsString('javascript:', $config['right_content']);
    }

    public function test_section_config_cleans_rich_text_inside_repeaters_only(): void
    {
        $config = HtmlSanitizer::sanitizeSectionConfig([
            'sections' => [[
                'title' => 'Safety',
                'content' => '<strong>Visible</strong><script>bad()</script>',
            ]],
        ], 'mandatory_disclosure', 'accordion');

        $this->assertSame('<strong>Visible</strong>', $config['sections'][0]['content']);
        $this->assertSame('Safety', $config['sections'][0]['title']);
    }

    public function test_embed_allows_https_youtube_iframe(): void
    {
        $html = '<iframe src="https://www.youtube.com/embed/abc" width="560" height="315"></iframe>';
        $clean = HtmlSanitizer::embed($html);

        $this->assertStringContainsString('youtube.com', $clean);
    }

    public function test_embed_blocks_untrusted_iframe_host(): void
    {
        $html = '<iframe src="https://evil.example/embed"></iframe>';
        $clean = HtmlSanitizer::embed($html);

        $this->assertStringNotContainsString('evil.example', $clean);
    }

    public function test_sanitize_config_cleans_known_keys(): void
    {
        $config = HtmlSanitizer::sanitizeConfig([
            'content' => '<p>Safe</p><script>x</script>',
            'map_embed' => '<iframe src="https://maps.google.com/?q=1"></iframe>',
            'heading' => 'Plain',
        ]);

        $this->assertStringNotContainsString('<script', $config['content']);
        $this->assertSame('Plain', $config['heading']);
    }
}
