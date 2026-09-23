<?php

namespace App\Services\State\Fest;

use App\Models\State\StateCertificate;
use App\Models\State\StateFestEvent;
use App\Support\PdfGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Phase 9 delivery — turning an issued certificate row into paper.
 *
 * Rendering goes through the configured Chromium service rather than DomPDF: a certificate is a
 * fixed-size laid-out page with webfonts and a QR panel, and DomPDF reflows it into something that
 * is not what anyone approved. A stale certificate is refused rather than printed, because a
 * certificate printed from a result that has since moved is worse than no certificate.
 */
class StateCertificateRenderer
{
    /** Certificates per ZIP. A whole State's merit certificates is thousands of pages. */
    public const ZIP_LIMIT = 500;

    public function html(StateCertificate $certificate, StateFestEvent $event): string
    {
        return view('state.print.certificate', [
            'certificate' => $certificate,
            'event' => $event,
            'typeLabel' => StateCertificate::TYPES[$certificate->type] ?? $certificate->type,
            'verifyUrl' => $this->verifyUrl($certificate),
        ])->render();
    }

    /** One certificate, inline in the browser so it can be checked before printing. */
    public function stream(StateCertificate $certificate, StateFestEvent $event)
    {
        $this->assertPrintable($certificate);

        $response = PdfGenerator::download(
            $this->html($certificate, $event),
            $this->filename($certificate),
            inline: true,
            isLandscape: true,
            requireBrowserRenderer: true,
        );

        $certificate->forceFill(['printed_at' => now()])->save();

        return $response;
    }

    /**
     * A print pack. Each certificate is its own file inside the ZIP, named by its number, so a
     * Sahodaya coordinator handed the pack can find one person's certificate without opening a
     * 400-page PDF.
     *
     * @param  Collection<int, StateCertificate>  $certificates
     * @return array{path: string, filename: string, included: int, skipped: list<string>}
     */
    public function zip(Collection $certificates, StateFestEvent $event): array
    {
        $printable = $certificates->reject(fn (StateCertificate $c) => $this->unprintableReason($c) !== null);
        $skipped = $certificates
            ->map(fn (StateCertificate $c) => ($r = $this->unprintableReason($c)) ? "{$c->certificate_number}: {$r}" : null)
            ->filter()->values()->all();

        abort_if($printable->isEmpty(), 409, 'Nothing here can be printed. '
            .($skipped ? implode(' ', array_slice($skipped, 0, 3)) : 'No certificates matched.'));
        abort_if(
            $printable->count() > self::ZIP_LIMIT,
            422,
            "That is {$printable->count()} certificates. Narrow by Sahodaya or item — a pack holds at most ".self::ZIP_LIMIT.'.',
        );

        $path = tempnam(sys_get_temp_dir(), 'state-certs-').'.zip';
        $zip = new \ZipArchive;
        abort_unless($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500, 'Could not open the ZIP for writing.');

        try {
            foreach ($printable as $certificate) {
                $zip->addFromString(
                    $this->zipEntryName($certificate),
                    PdfGenerator::render(
                        $this->html($certificate, $event),
                        isLandscape: true,
                        requireBrowserRenderer: true,
                    ),
                );
            }

            // A manifest, because a pack of PDFs with no index is impossible to reconcile against
            // the tally report six weeks later.
            $zip->addFromString('manifest.csv', $this->manifest($printable, $skipped));
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($path);

            throw $e;
        }

        $zip->close();

        StateCertificate::whereIn('id', $printable->pluck('id'))->update(['printed_at' => now()]);

        return [
            'path' => $path,
            'filename' => \Str::slug($event->name.' certificates').'.zip',
            'included' => $printable->count(),
            'skipped' => $skipped,
        ];
    }

    public function verifyUrl(StateCertificate $certificate): string
    {
        return URL::to('/state/certificates/verify/'.$certificate->verification_code);
    }

    private function assertPrintable(StateCertificate $certificate): void
    {
        $reason = $this->unprintableReason($certificate);

        abort_if($reason !== null, 409, $reason);
    }

    private function unprintableReason(StateCertificate $certificate): ?string
    {
        return match ($certificate->status) {
            StateCertificate::STALE => 'This certificate no longer matches its result. Regenerate it before printing.',
            StateCertificate::SUPERSEDED => 'This certificate was replaced by a newer one.',
            default => null,
        };
    }

    private function filename(StateCertificate $certificate): string
    {
        return \Str::slug($certificate->certificate_number.' '.$certificate->recipient_name).'.pdf';
    }

    /** Foldered by Sahodaya: that is the unit a pack gets handed to. */
    private function zipEntryName(StateCertificate $certificate): string
    {
        $folder = \Str::slug($certificate->sahodaya_name ?: 'unattributed');

        return $folder.'/'.$this->filename($certificate);
    }

    /** @param  Collection<int, StateCertificate>  $included */
    private function manifest(Collection $included, array $skipped): string
    {
        $out = fopen('php://temp', 'r+');
        \App\Support\CsvSafety::fputcsv($out, ['Number', 'Type', 'Recipient', 'Sahodaya', 'School', 'Item', 'Position', 'Grade', 'Verification code']);

        foreach ($included as $c) {
            \App\Support\CsvSafety::fputcsv($out, [
                $c->certificate_number, $c->type, $c->recipient_name, $c->sahodaya_name, $c->school_name,
                $c->item_code, $c->position, $c->grade, $c->verification_code,
            ]);
        }

        foreach ($skipped as $line) {
            \App\Support\CsvSafety::fputcsv($out, ['SKIPPED', $line]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
