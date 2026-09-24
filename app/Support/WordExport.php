<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Hand-rolled, dependency-free .docx generator — same reasoning as ExcelExport's hand-rolled
 * SpreadsheetML: no Composer package (PhpWord etc.) for what is structurally a handful of
 * headings and tables. Builds the minimal valid OOXML WordprocessingML package by hand (a
 * .docx is just a zip of a few small XML parts) rather than faking a legacy .doc-as-HTML file,
 * so it opens in Word without a "content doesn't match extension" warning.
 */
class WordExport
{
    /**
     * @param  list<array{heading?: ?string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}>  $sections  one table per section, each with an optional heading above it
     */
    public static function download(string $filename, string $title, array $sections, ?string $generatedNote = null): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.docx')) {
            $filename .= '.docx';
        }

        $bytes = self::buildPackage($title, $sections, $generatedNote);

        return response()->streamDownload(
            fn () => print($bytes),
            $filename,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    /** @param  list<array{heading?: ?string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}>  $sections */
    private static function buildPackage(string $title, array $sections, ?string $generatedNote): string
    {
        // ZipArchive has no in-memory target, only a real file — same tradeoff Laravel itself
        // makes for zip exports. Built in the OS temp dir and read back into a string so the
        // caller (and its tests) see a plain byte string, not a lingering file to clean up.
        $path = tempnam(sys_get_temp_dir(), 'docx');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::packageRelsXml());
        $zip->addFromString('docProps/core.xml', self::corePropsXml($title));
        $zip->addFromString('docProps/app.xml', self::appPropsXml());
        $zip->addFromString('word/_rels/document.xml.rels', self::documentRelsXml());
        $zip->addFromString('word/document.xml', self::documentXml($title, $sections, $generatedNote));
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private static function contentTypesXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
            <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
            <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
        </Types>
        XML;
    }

    private static function packageRelsXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
            <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
            <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
        </Relationships>
        XML;
    }

    private static function documentRelsXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
        XML;
    }

    private static function corePropsXml(string $title): string
    {
        $now = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.self::escape($title).'</dc:title>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private static function appPropsXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
            <Application>Kalotsav Platform</Application>
        </Properties>
        XML;
    }

    /** @param  list<array{heading?: ?string, headers: list<string>, rows: iterable<int, list<string|int|float|null>>}>  $sections */
    private static function documentXml(string $title, array $sections, ?string $generatedNote): string
    {
        $body = self::titleParagraph($title);

        if ($generatedNote !== null) {
            $body .= self::paragraph($generatedNote, sizeHalfPoints: 16, italic: true, color: '64748B');
        }

        foreach ($sections as $section) {
            if (filled($section['heading'] ?? null)) {
                $body .= self::heading($section['heading']);
            }

            $body .= self::table($section['headers'], $section['rows']);
            $body .= '<w:p/>';
        }

        $body .= self::sectionProperties();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body.'</w:body>'
            .'</w:document>';
    }

    private static function titleParagraph(string $title): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t xml:space="preserve">'.self::escape($title).'</w:t></w:r>'
            .'</w:p>';
    }

    private static function heading(string $text): string
    {
        return '<w:p><w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.self::escape($text).'</w:t></w:r>'
            .'</w:p>';
    }

    private static function paragraph(string $text, int $sizeHalfPoints = 20, bool $italic = false, string $color = '000000'): string
    {
        $rPr = '<w:sz w:val="'.$sizeHalfPoints.'"/><w:color w:val="'.$color.'"/>'.($italic ? '<w:i/>' : '');

        return '<w:p><w:r><w:rPr>'.$rPr.'</w:rPr><w:t xml:space="preserve">'.self::escape($text).'</w:t></w:r></w:p>';
    }

    /** @param  list<string>  $headers  @param  iterable<int, list<string|int|float|null>>  $rows */
    private static function table(array $headers, iterable $rows): string
    {
        $borders = '<w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'<w:left w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'<w:right w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="94A3B8"/>'
            .'</w:tblBorders>';

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/>'.$borders.'</w:tblPr>';

        $xml .= '<w:tr>';
        foreach ($headers as $header) {
            $xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:fill="1E293B"/></w:tcPr>'
                .'<w:p><w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="18"/></w:rPr>'
                .'<w:t xml:space="preserve">'.self::escape((string) $header).'</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';

        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            foreach ($row as $cell) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                    .'<w:p><w:r><w:rPr><w:sz w:val="18"/></w:rPr>'
                    .'<w:t xml:space="preserve">'.self::escape((string) ($cell ?? '')).'</w:t></w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private static function sectionProperties(): string
    {
        return '<w:sectPr>'
            .'<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            .'<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/>'
            .'</w:sectPr>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
