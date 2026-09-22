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
}
