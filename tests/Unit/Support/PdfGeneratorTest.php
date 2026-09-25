<?php

namespace Tests\Unit\Support;

use App\Support\PdfGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PdfGeneratorTest extends TestCase
{
    public function test_browser_renderer_can_be_required_for_exact_layouts(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render']);
        Http::fake([
            'https://pdf.example.test/render' => Http::response('%PDF-browser-rendered', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $pdf = PdfGenerator::render(
            '<html><body>ID card</body></html>',
            pageWidthMm: 480.06,
            pageHeightMm: 314.96,
            requireBrowserRenderer: true,
        );

        $this->assertSame('%PDF-browser-rendered', $pdf);
        Http::assertSent(fn ($request) => $request['width'] === '480.06mm'
            && $request['height'] === '314.96mm'
            && $request['printBackground'] === true);
    }

    public function test_required_browser_renderer_never_silently_falls_back_to_dompdf(): void
    {
        config(['services.pdf_converter.url' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('browser PDF converter is not configured');

        PdfGenerator::render(
            '<html><body>ID card</body></html>',
            requireBrowserRenderer: true,
        );
    }

    public function test_a_configured_converter_that_fails_is_reported_not_replaced_by_dompdf(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render']);
        Http::fake(['https://pdf.example.test/render' => Http::response('boom', 500)]);

        try {
            PdfGenerator::download('<html><body>Report</body></html>', 'report.pdf');
            $this->fail('Expected a 503 when the converter fails.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }
    }

    public function test_dompdf_fallback_is_still_available_when_explicitly_enabled(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render', 'services.pdf_converter.fallback' => true]);
        Http::fake(['https://pdf.example.test/render' => Http::response('boom', 500)]);

        $response = PdfGenerator::download('<html><body>Report</body></html>', 'report.pdf');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_dompdf_is_used_when_no_converter_is_configured_at_all(): void
    {
        config(['services.pdf_converter.url' => null]);

        $this->assertSame(200, PdfGenerator::download('<html><body>Local dev</body></html>', 'report.pdf')->getStatusCode());
    }

    public function test_render_many_sends_every_document_to_the_converter_keyed_by_its_own_key(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render', 'services.pdf_converter.concurrency' => 2]);
        Http::fake(fn ($request) => Http::response('%PDF-'.strip_tags($request['html']), 200));

        $results = PdfGenerator::renderMany([
            '7:bg' => ['html' => '<p>seven-bg</p>', 'isLandscape' => true],
            '7:plain' => ['html' => '<p>seven-plain</p>', 'isLandscape' => true],
            '8:bg' => ['html' => '<p>eight-bg</p>', 'pageWidthMm' => 297.0, 'pageHeightMm' => 210.0],
        ]);

        $this->assertSame(['7:bg' => '%PDF-seven-bg', '7:plain' => '%PDF-seven-plain', '8:bg' => '%PDF-eight-bg'], $results);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['html'] === '<p>eight-bg</p>' && $request['width'] === '297mm');
        Http::assertSent(fn ($request) => $request['html'] === '<p>seven-bg</p>' && $request['landscape'] === true && $request['format'] === 'A4');
    }

    public function test_render_many_reports_a_failed_document_without_losing_the_others(): void
    {
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render']);
        Http::fake(fn ($request) => str_contains($request['html'], 'bad')
            ? Http::response('boom', 500)
            : Http::response('%PDF-ok', 200));

        $results = PdfGenerator::renderMany([
            'good' => ['html' => '<p>good</p>'],
            'bad' => ['html' => '<p>bad</p>'],
        ]);

        $this->assertSame('%PDF-ok', $results['good']);
        // Retried through render() on its own, so it fails exactly the way render() does.
        $this->assertInstanceOf(RuntimeException::class, $results['bad']);
    }

    public function test_render_many_without_a_converter_renders_each_document_locally(): void
    {
        config(['services.pdf_converter.url' => null]);

        $results = PdfGenerator::renderMany(['a' => ['html' => '<p>A</p>'], 'b' => ['html' => '<p>B</p>']]);

        $this->assertSame(['a', 'b'], array_keys($results));
        $this->assertStringStartsWith('%PDF', $results['a']);
        $this->assertStringStartsWith('%PDF', $results['b']);
    }
}
