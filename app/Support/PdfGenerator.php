<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfGenerator
{
    public static function download(string $html, string $filename, bool $inline = false, bool $isLandscape = true)
    {
        $url = env('PDF_CONVERTER_URL');

        if ($url) {
            $response = Http::timeout(300)->post($url, [
                'html'            => $html,
                'landscape'       => $isLandscape,
                'printBackground' => true,
                'format'          => 'A4',
                'margin'          => [
                    'top'    => '0',
                    'bottom' => '0',
                    'left'   => '0',
                    'right'  => '0',
                ]
            ]);

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
