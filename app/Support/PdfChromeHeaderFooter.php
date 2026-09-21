<?php

namespace App\Support;

class PdfChromeHeaderFooter
{
    /**
     * Renders the exact same partials.pdf-report-heading Blade partial every
     * dompdf-rendered fest report uses, wrapped for Puppeteer's isolated header iframe
     * (it has no access to the page's own <style> block or <body> font-family, so those
     * have to be set explicitly on this wrapper). This is what makes the Chromium-
     * rendered header look identical to the dompdf one -- one shared template instead
     * of two hand-maintained copies that drift apart from each other over time.
     *
     * @param  array{orgName?: string, logoSrc?: ?string, docTitle?: string, eventTitle: string, item?: mixed, categoryLabel?: ?string, participantCount?: ?int, itemLine?: ?string}  $heading
     * @return array{0: string, 1: string} [headerHtml, footerHtml]
     */
    public static function build(array $heading): array
    {
        $headingHtml = view('partials.pdf-report-heading', $heading)->render();

        $header = <<<HTML
            <div style="width:100%; font-family:Arial,Helvetica,sans-serif; padding:0 28px; box-sizing:border-box;">
                {$headingHtml}
            </div>
            HTML;

        $orgName = e($heading['orgName'] ?? 'Sahodaya');
        $eventTitle = e($heading['eventTitle'] ?? '');
        $generated = e(now()->format('d M Y, h:i A'));

        $footer = <<<HTML
            <div style="width:100%; font-family:Arial,Helvetica,sans-serif; font-size:7px; color:#64748b; padding:0 28px; box-sizing:border-box; display:flex; justify-content:space-between; border-top:1px solid #cbd5e1; padding-top:4px;">
                <span>{$orgName} &bull; {$eventTitle} &bull; Generated {$generated}</span>
                <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
            </div>
            HTML;

        return [$header, $footer];
    }
}
