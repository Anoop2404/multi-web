<?php

namespace Tests\Unit\Services\Certificates;

use App\Services\Certificates\CertificateBackgroundConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers storeFromUpload()'s $preConvertedPng parameter: a certificate background PDF
 * uploaded alongside a page-1 PNG already rendered client-side (pdf.js, in
 * Templates.vue's onFileChange) must persist that PNG as-is, without ever calling
 * pdfFirstPageToPng() to rasterize the PDF itself server-side. That's what lets a PDF
 * background save successfully on a server with neither Imagick nor pdftoppm/qlmanage
 * installed — previously every PDF upload failed outright on such a server.
 */
class CertificateBackgroundConverterTest extends TestCase
{
    public function test_a_preconverted_png_is_stored_as_is_without_server_side_pdf_rasterization(): void
    {
        Storage::fake('public');

        // Content is irrelevant here -- when a pre-converted PNG is supplied, the PDF's
        // own bytes are never parsed or rasterized, only stored as the original file.
        $pdf = UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf');

        $png = UploadedFile::fake()->image('converted.png', 20, 15);
        $expectedBytes = file_get_contents($png->getRealPath());

        $result = app(CertificateBackgroundConverter::class)
            ->storeFromUpload($pdf, 'certificate-templates-test', 'public', $png);

        $this->assertSame('landscape', $result['orientation']);
        Storage::disk('public')->assertExists($result['template_file_path']);
        Storage::disk('public')->assertExists($result['background_path']);

        $storedBackground = Storage::disk('public')->get($result['background_path']);
        $this->assertSame(
            $expectedBytes,
            $storedBackground,
            'The stored background must be exactly the client-rendered PNG, proving the '
            .'PDF itself was never rasterized server-side.'
        );
    }

    public function test_without_a_preconverted_png_the_pdf_is_rasterized_server_side_as_before(): void
    {
        Storage::fake('public');

        $realPdfPath = base_path('tests/Fixtures/sample-certificate.pdf');
        if (! is_file($realPdfPath)) {
            $this->markTestSkipped('No real sample PDF fixture available to rasterize.');
        }

        $pdf = new UploadedFile($realPdfPath, 'certificate.pdf', 'application/pdf', null, true);

        $result = app(CertificateBackgroundConverter::class)
            ->storeFromUpload($pdf, 'certificate-templates-test', 'public');

        Storage::disk('public')->assertExists($result['background_path']);
        $bytes = Storage::disk('public')->get($result['background_path']);
        $this->assertNotSame('', $bytes);
        $this->assertStringStartsWith("\x89PNG", $bytes);
    }
}
