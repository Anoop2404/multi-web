<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfGenerator
{
    /**
     * @param  ?string  $headerTemplate  Puppeteer header/footer templates are rendered
     *                                    by Chromium's own print pipeline, isolated from
     *                                    the page content — a self-contained HTML
     *                                    fragment with inline styles (external/page
     *                                    stylesheets don't apply). Supports the special
     *                                    classes `pageNumber`/`totalPages`/`date`/`title`/
     *                                    `url`, which Chromium fills in automatically.
     *                                    This is the one repeat-per-page mechanism that's
     *                                    actually native to the renderer, rather than a
     *                                    CSS trick layered on top of the page content.
     *                                    Only used when PDF_CONVERTER_URL (the external
     *                                    Chromium/Puppeteer service) is active; ignored
     *                                    on the dompdf fallback.
     * @param  ?string  $footerTemplate  Same rules as $headerTemplate.
     * @param  ?array{top?: string, right?: string, bottom?: string, left?: string}  $margin
     *                                    Page margins as CSS length strings (e.g. "70px").
     *                                    Needs to be large enough to fit the header/footer
     *                                    templates when those are supplied — Chromium
     *                                    reserves exactly this much space for them and
     *                                    won't let page content overlap it.
     */
    public static function download(
        string $html,
        string $filename,
        bool $inline = false,
        bool $isLandscape = false,
        ?string $headerTemplate = null,
        ?string $footerTemplate = null,
        ?array $margin = null,
    ) {
        $url = env('PDF_CONVERTER_URL');

        if ($url) {
            $hasHeaderFooter = $headerTemplate !== null || $footerTemplate !== null;

            $payload = [
                'html'            => $html,
                'landscape'       => $isLandscape,
                'printBackground' => true,
                'format'          => 'A4',
                'margin'          => $margin ?? [
                    'top'    => '0',
                    'bottom' => '0',
                    'left'   => '0',
                    'right'  => '0',
                ],
            ];

            if ($hasHeaderFooter) {
                $payload['displayHeaderFooter'] = true;
                $payload['headerTemplate'] = $headerTemplate ?? '<span></span>';
                $payload['footerTemplate'] = $footerTemplate ?? '<span></span>';
            }

            $response = Http::timeout(300)->post($url, $payload);

            if ($response->successful()) {
                if ($inline) {
                    return response()->stream(function () use ($response) {
                        echo $response->body();
                    }, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    ]);
                }
                
                return response()->streamDownload(function () use ($response) {
                    echo $response->body();
                }, $filename, ['Content-Type' => 'application/pdf']);
            }

            throw new \Exception("External PDF generation failed: " . $response->status() . " - " . $response->body());
        }

        // Fallback to DomPDF
        $pdf = Pdf::loadHTML($html);
        if ($isLandscape) {
            $pdf->setPaper('A4', 'landscape');
        }
        
        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
