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
