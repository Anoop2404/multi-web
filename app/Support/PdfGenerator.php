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
     * @param  ?float  $pageWidthMm      Custom physical page size — already oriented (the
     *                                    wider figure for landscape), overriding the A4
     *                                    default. Both this and $pageHeightMm must be set
     *                                    together; $isLandscape is ignored when they are,
     *                                    since the dimensions already encode orientation.
     * @param  ?float  $pageHeightMm     See $pageWidthMm.
     */
    public static function download(
        string $html,
        string $filename,
        bool $inline = false,
        bool $isLandscape = false,
        ?string $headerTemplate = null,
        ?string $footerTemplate = null,
        ?array $margin = null,
        ?float $pageWidthMm = null,
        ?float $pageHeightMm = null,
    ) {
        $url = self::resolveConverterUrl(config('services.pdf_converter.url'));
        $hasCustomSize = $pageWidthMm && $pageHeightMm;

        if ($url) {
            $hasHeaderFooter = $headerTemplate !== null || $footerTemplate !== null;
            $resolvedMargin = $margin ?? ['top' => '0', 'bottom' => '0', 'left' => '0', 'right' => '0'];

            $payload = [
                'html'            => $html,
                'printBackground' => true,
                'timeout'         => 120000,
                // Sent both nested (spec-correct Puppeteer shape) and flat/mm (this
                // deployment's chrome-print-server.js reads marginTop/Right/Bottom/Left as
                // plain numbers, not the nested object) -- see marginToMm()'s docblock.
                'margin'          => $resolvedMargin,
                'marginTop'       => self::marginToMm($resolvedMargin['top'] ?? 0),
                'marginRight'     => self::marginToMm($resolvedMargin['right'] ?? 0),
                'marginBottom'    => self::marginToMm($resolvedMargin['bottom'] ?? 0),
                'marginLeft'      => self::marginToMm($resolvedMargin['left'] ?? 0),
            ];

            if ($hasCustomSize) {
                $payload['width'] = $pageWidthMm.'mm';
                $payload['height'] = $pageHeightMm.'mm';
            } else {
                $payload['landscape'] = $isLandscape;
                $payload['format'] = 'A4';
            }

            if ($hasHeaderFooter) {
                $payload['displayHeaderFooter'] = true;
                $payload['headerTemplate'] = $headerTemplate ?? '<span></span>';
                $payload['footerTemplate'] = $footerTemplate ?? '<span></span>';
            }

            try {
                $response = Http::connectTimeout(3)
                    ->timeout((int) config('services.pdf_converter.timeout', 300))
                    ->post($url, $payload);

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
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('External PDF generation failed, falling back to DomPDF: ' . $e->getMessage());
            }
        }

        // Fallback to DomPDF
        $pdf = Pdf::loadHTML($html);
        if ($hasCustomSize) {
            $pdf->setPaper([0, 0, self::mmToPoints($pageWidthMm), self::mmToPoints($pageHeightMm)]);
        } elseif ($isLandscape) {
            $pdf->setPaper('A4', 'landscape');
        }

        // DomPDF does not replace {PAGE_NUM}/{PAGE_COUNT} tokens written inside
        // ordinary HTML. When a caller supplied a footer template (the Chromium
        // path's signal that page furniture is required), draw the real page count
        // directly on DomPDF's canvas instead. Drawn bottom-LEFT deliberately --
        // partials/pdf-generated-footer.blade.php's "Generated on ..." timestamp
        // (included by every one of these same report types) is right-aligned at the
        // very bottom edge, and this used to sit at almost the same bottom-right spot,
        // visibly overlapping it.
        if ($footerTemplate !== null) {
            $pdf->render();

            $domPdf = $pdf->getDomPDF();
            $canvas = $domPdf->getCanvas();
            $font = $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $fontSize = 7;
            $canvas->page_text(
                38,
                $canvas->get_height() - 28,
                $label,
                $font,
                $fontSize,
                [0.39, 0.45, 0.55]
            );
        }

        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /**
     * Render HTML to raw PDF string content using external Chromium PDF service (PDF_CONVERTER_URL) if configured,
     * or fallback to DomPDF.
     */
    public static function render(
        string $html,
        bool $isLandscape = false,
        ?string $headerTemplate = null,
        ?string $footerTemplate = null,
        ?array $margin = null,
        ?float $pageWidthMm = null,
        ?float $pageHeightMm = null,
        int $timeoutMs = 300000,
    ): string {
        $url = self::resolveConverterUrl(config('services.pdf_converter.url'));
        $hasCustomSize = $pageWidthMm && $pageHeightMm;

        if ($url) {
            $hasHeaderFooter = $headerTemplate !== null || $footerTemplate !== null;
            $resolvedMargin = $margin ?? ['top' => '0', 'bottom' => '0', 'left' => '0', 'right' => '0'];

            $payload = [
                'html'            => $html,
                'printBackground' => true,
                'timeout'         => $timeoutMs,
                'margin'          => $resolvedMargin,
                'marginTop'       => self::marginToMm($resolvedMargin['top'] ?? 0),
                'marginRight'     => self::marginToMm($resolvedMargin['right'] ?? 0),
                'marginBottom'    => self::marginToMm($resolvedMargin['bottom'] ?? 0),
                'marginLeft'      => self::marginToMm($resolvedMargin['left'] ?? 0),
            ];

            if ($hasCustomSize) {
                $payload['width'] = $pageWidthMm.'mm';
                $payload['height'] = $pageHeightMm.'mm';
            } else {
                $payload['landscape'] = $isLandscape;
                $payload['format'] = 'A4';
            }

            if ($hasHeaderFooter) {
                $payload['displayHeaderFooter'] = true;
                $payload['headerTemplate'] = $headerTemplate ?? '<span></span>';
                $payload['footerTemplate'] = $footerTemplate ?? '<span></span>';
            }

            try {
                $httpTimeout = (int) max(config('services.pdf_converter.timeout', 300), ceil($timeoutMs / 1000) + 30);
                $response = Http::connectTimeout(3)
                    ->timeout($httpTimeout)
                    ->post($url, $payload);

                if ($response->successful()) {
                    return $response->body();
                }

                \Illuminate\Support\Facades\Log::warning('External PDF generation failed with status ' . $response->status() . ', falling back to DomPDF');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('External PDF render failed, falling back to DomPDF: ' . $e->getMessage());
            }
        }

        // Fallback to DomPDF
        $pdf = Pdf::loadHTML($html);
        if ($hasCustomSize) {
            $pdf->setPaper([0, 0, self::mmToPoints($pageWidthMm), self::mmToPoints($pageHeightMm)]);
        } elseif ($isLandscape) {
            $pdf->setPaper('A4', 'landscape');
        }

        // Drawn bottom-LEFT -- see the matching comment in download() above.
        if ($footerTemplate !== null) {
            $pdf->render();

            $domPdf = $pdf->getDomPDF();
            $canvas = $domPdf->getCanvas();
            $font = $domPdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $fontSize = 7;
            $canvas->page_text(
                38,
                $canvas->get_height() - 28,
                $label,
                $font,
                $fontSize,
                [0.39, 0.45, 0.55],
            );

            return $domPdf->output();
        }

        return $pdf->output();
    }

    /** DomPDF's setPaper() takes a custom size as points (72/inch), not mm. */
    private static function mmToPoints(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    /**
     * Converts a CSS length string ('32mm', '112px', '1in', or a bare number already in
     * mm) to a raw millimeter float. Sent alongside the nested `margin` object as flat
     * marginTop/Right/Bottom/Left fields, since this deployment's external converter
     * (chrome-print-server.js) reads those flat fields as plain numbers rather than the
     * nested object Puppeteer's own API expects -- sending both means margins apply
     * correctly regardless of which shape that service happens to parse.
     */
    private static function marginToMm(string|int|float $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/^(-?[\d.]+)\s*(px|mm|cm|in)?$/', trim((string) $value), $m)) {
            $num = (float) $m[1];

            return match ($m[2] ?? 'mm') {
                'px' => $num * 25.4 / 96,
                'in' => $num * 25.4,
                'cm' => $num * 10,
                default => $num,
            };
        }

        return 0.0;
    }

    /**
     * Normalizes the external PDF converter URL. If a base host/origin was provided
     * (e.g. 'https://pdf.truecampus.in'), automatically appends '/generate-pdf'
     * so it doesn't fail with a 404 at the root path.
     */
    public static function resolveConverterUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $trimmed = trim($url);
        $path = parse_url($trimmed, PHP_URL_PATH);

        if ($path === null || $path === '' || $path === '/') {
            return rtrim($trimmed, '/') . '/generate-pdf';
        }

        return $trimmed;
    }
}
