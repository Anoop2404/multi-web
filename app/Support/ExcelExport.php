<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExport
{
    /** "Generated on 06 Sep 2026, 03:45 PM" — the standard wording used across every
     *  fest report (Blade PDFs, CSVs, and this class), so a caller doesn't need to
     *  repeat the format string themselves. */
    public static function generatedOnNote(): string
    {
        return 'Generated on '.now()->format('d M Y, h:i A');
    }

    /**
     * Named body-cell styles a caller can assign to specific columns via
     * $columnStyles, matching the same colour language the web page/PDF already use
     * for this exact matrix shape: 'cat-a'/'cat-b' alternate per category so a reader
     * can see where one category's block of columns ends and the next begins even in
     * this format's flat, single-row header (no merged multi-tier band like the PDF
     * has); 'sub' and 'overall' pick out a category's own subtotal and the combined
     * total the same way the PDF's .subtotal-col/.overall-col do.
     */
    public const CATEGORY_BAND_STYLES = ['cat-a', 'cat-b'];

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     * @param  string|null  $generatedNote  Optional note row (e.g. self::generatedOnNote())
     *                                      written above the header row in every sheet.
     * @param  list<int>  $verticalHeaderIndices  Zero-based $headers indices to render
     *                                            rotated (reads bottom-to-top), matching
     *                                            the vertical item-name columns already
     *                                            used on the web page and PDF for a wide
     *                                            school x item matrix — keeps those
     *                                            columns narrow instead of one-column-
     *                                            per-long-header-string wide.
     * @param  array<int, string>  $columnStyles  Zero-based $headers indices whose body
     *                                            cells (every row) should use a named
     *                                            style instead of the default zebra
     *                                            body/body-alt — one of 'cat-a', 'cat-b'
     *                                            (CATEGORY_BAND_STYLES), 'sub', or
     *                                            'overall'. The header cell itself is
     *                                            unaffected (always the standard navy
     *                                            header/header-vertical look).
     */
    public static function download(string $filename, array $headers, iterable $rows, ?string $generatedNote = null, array $verticalHeaderIndices = [], array $columnStyles = []): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return response()->streamDownload(
            fn () => print(self::spreadsheetXml($headers, $rows, $generatedNote, $verticalHeaderIndices, $columnStyles)),
            $filename,
            [
                'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     * @param  list<int>  $verticalHeaderIndices  See download()'s docblock.
     * @param  array<int, string>  $columnStyles  See download()'s docblock.
     */
    public static function spreadsheetXml(array $headers, iterable $rows, ?string $generatedNote = null, array $verticalHeaderIndices = [], array $columnStyles = []): string
    {
        return self::workbookXml(['Sheet1' => ['headers' => $headers, 'rows' => $rows, 'verticalHeaderIndices' => $verticalHeaderIndices, 'columnStyles' => $columnStyles]], $generatedNote);
    }

    /**
     * Multi-sheet workbook — one tab per entry in $sheets, in the order given.
     *
     * @param  array<string, array{headers: list<string>, rows: iterable<int, list<string|int|float|null>>, verticalHeaderIndices?: list<int>, columnStyles?: array<int, string>}>  $sheets  sheet name => {headers, rows, verticalHeaderIndices?, columnStyles?}
     * @param  string|null  $generatedNote  Optional note row written above the header row in every sheet.
     */
    public static function downloadMultiSheet(string $filename, array $sheets, ?string $generatedNote = null): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return response()->streamDownload(
            fn () => print(self::workbookXml($sheets, $generatedNote)),
            $filename,
            [
                'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    /**
     * @param  array<string, array{headers: list<string>, rows: iterable<int, list<string|int|float|null>>, verticalHeaderIndices?: list<int>, columnStyles?: array<int, string>}>  $sheets
     */
    private static function workbookXml(array $sheets, ?string $generatedNote = null): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";

        // Bold, shaded header row + normal body row, referenced below via ss:StyleID —
        // without a Styles block every cell (header and data alike) renders identically
        // plain, which is why the header row was indistinguishable from data before this.
        // "header-vertical" is the same header look, rotated 90° (reads bottom-to-top) —
        // applied per-cell (not row-wide) to just the columns a caller flags via
        // verticalHeaderIndices, matching the vertical item-name columns already used on
        // the web page/PDF for a wide school x item matrix.
        //
        // "cat-a"/"cat-b"/"sub"/"overall" are per-column body-cell overrides (see
        // download()'s $columnStyles docblock) — same colour language as the PDF/web's
        // .item-col category tint, .subtotal-col and .overall-col, so a reader can tell
        // a category's columns apart and pick out its Sub/the OVERALL total at a glance
        // even in this format's flat single-row header.
        // Every body/header style below gets the same thin, light-gray grid on all four
        // sides -- SpreadsheetML draws NO border at all unless a style says so explicitly
        // (there's no implicit-gridlines fallback once ss:StyleID is assigned to a cell,
        // unlike a plain unstyled sheet), so without this every exported "Excel" file
        // opened with cell fills but no visible cell separators whatsoever.
        $border = '<Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D7DE"/>'
            .'<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D7DE"/>'
            .'<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D7DE"/>'
            .'<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D0D7DE"/></Borders>';

        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="header-vertical"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/><Alignment ss:Vertical="Bottom" ss:Horizontal="Center" ss:Rotate="90"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="body"><Alignment ss:Vertical="Center"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="body-alt"><Alignment ss:Vertical="Center"/><Interior ss:Color="#F7F9FC" ss:Pattern="Solid"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="cat-a"><Alignment ss:Vertical="Center"/><Interior ss:Color="#EFF4FB" ss:Pattern="Solid"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="cat-b"><Alignment ss:Vertical="Center"/><Interior ss:Color="#F7F7F2" ss:Pattern="Solid"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="sub"><Font ss:Bold="1" ss:Color="#1D3557"/><Alignment ss:Vertical="Center"/><Interior ss:Color="#DDE6F2" ss:Pattern="Solid"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="overall"><Font ss:Bold="1" ss:Color="#1D3557"/><Alignment ss:Vertical="Center"/><Interior ss:Color="#C8D6EA" ss:Pattern="Solid"/>'.$border.'</Style>';
        $xml .= '<Style ss:ID="note"><Font ss:Italic="1" ss:Color="#64748B"/></Style>';
        $xml .= '</Styles>'."\n";

        // Excel sheet names: no fresh worksheet at all is invalid, so an empty $sheets
        // list still gets one blank "Sheet1" rather than a corrupt/unopenable file.
        if ($sheets === []) {
            $sheets = ['Sheet1' => ['headers' => [], 'rows' => []]];
        }

        $usedNames = [];
        foreach ($sheets as $name => $sheet) {
            $safeName = self::uniqueSheetName((string) $name, $usedNames);
            // Materialized once — computing column widths needs to walk every row, and a
            // caller may hand us a one-shot generator that row-writing below would then
            // find already exhausted.
            $rows = is_array($sheet['rows']) ? $sheet['rows'] : iterator_to_array($sheet['rows']);
            $verticalHeaderIndices = array_flip($sheet['verticalHeaderIndices'] ?? []);
            $columnStyles = $sheet['columnStyles'] ?? [];

            $xml .= '<Worksheet ss:Name="'.$escape($safeName).'">';
            $xml .= '<Table>'."\n";
            $xml .= self::columnWidthsXml($sheet['headers'], $rows, $verticalHeaderIndices);

            if ($generatedNote !== null && $generatedNote !== '') {
                $mergeAcross = max(0, count($sheet['headers']) - 1);
                $xml .= '<Row ss:StyleID="note"><Cell ss:MergeAcross="'.$mergeAcross.'"><Data ss:Type="String">'.$escape($generatedNote).'</Data></Cell></Row>'."\n";
            }

            $xml .= '<Row'.self::headerRowHeightAttr($sheet['headers'], $verticalHeaderIndices).'>';
            foreach ($sheet['headers'] as $i => $header) {
                $styleId = isset($verticalHeaderIndices[$i]) ? 'header-vertical' : 'header';
                $xml .= '<Cell ss:StyleID="'.$styleId.'"><Data ss:Type="String">'.$escape($header).'</Data></Cell>';
            }
            $xml .= '</Row>'."\n";

            // Alternating row shading (every other row) — same zebra-striping already
            // used on every fest report's web page/PDF, so a large sheet like the
            // Consolidated Report's 25+ schools stays readable instead of one
            // undifferentiated wall of rows. A column flagged in $columnStyles (a
            // category band, Sub, or OVERALL) keeps its own fixed colour regardless of
            // row, same as the PDF/web — those columns are meant to stand out, not
            // blend into the zebra stripe.
            $rowIndex = 0;
            foreach ($rows as $row) {
                $rowStyleId = $rowIndex % 2 === 1 ? 'body-alt' : 'body';
                $xml .= '<Row ss:StyleID="'.$rowStyleId.'">';
                foreach ($row as $i => $cell) {
                    $type = is_numeric($cell) && $cell !== '' && $cell !== null ? 'Number' : 'String';
                    $cellStyleId = $columnStyles[$i] ?? null;
                    $cellStyleAttr = $cellStyleId !== null ? ' ss:StyleID="'.$cellStyleId.'"' : '';
                    $xml .= '<Cell'.$cellStyleAttr.'><Data ss:Type="'.$type.'">'.$escape($cell).'</Data></Cell>';
                }
                $xml .= '</Row>'."\n";
                $rowIndex++;
            }

            $xml .= '</Table>';
            $xml .= '</Worksheet>'."\n";
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    /**
     * Header row height tall enough for the longest vertical (rotated) header label,
     * rotated text needing roughly the same per-character allowance columnWidthsXml()
     * already uses for horizontal width, just applied to height instead. No-op (Excel's
     * own default row height) when nothing in this sheet is rotated.
     *
     * @param  list<string>  $headers
     * @param  array<int, true>  $verticalHeaderIndices
     */
    private static function headerRowHeightAttr(array $headers, array $verticalHeaderIndices): string
    {
        if ($verticalHeaderIndices === []) {
            return '';
        }

        $maxLen = 0;
        foreach ($verticalHeaderIndices as $i => $true) {
            $maxLen = max($maxLen, mb_strlen((string) ($headers[$i] ?? '')));
        }

        $height = (int) max(80, min(500, ($maxLen + 2) * 6));

        return ' ss:Height="'.$height.'"';
    }

    /**
     * Column widths sized to the widest of the header or any cell in that column
     * (sampled across every row — exports here run to at most a few thousand rows, so a
     * full scan is cheap), clamped to a readable range. SpreadsheetML ss:Width is in
     * points; ~6.5pt per character is a reasonable approximation for the default font.
     *
     * A column flagged in $verticalHeaderIndices skips the header's own (rotated, so no
     * longer width-relevant) length — its width is driven by its data cells alone,
     * keeping it narrow instead of as wide as its long header string.
     *
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  array<int, true>  $verticalHeaderIndices
     */
    private static function columnWidthsXml(array $headers, array $rows, array $verticalHeaderIndices = []): string
    {
        $maxLen = [];
        foreach ($headers as $i => $header) {
            $maxLen[$i] = isset($verticalHeaderIndices[$i]) ? 0 : mb_strlen((string) $header);
        }

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $len = mb_strlen((string) ($cell ?? ''));
                if ($len > ($maxLen[$i] ?? 0)) {
                    $maxLen[$i] = $len;
                }
            }
        }

        $xml = '';
        foreach ($maxLen as $len) {
            $width = (int) max(60, min(320, ($len + 3) * 6.5));
            $xml .= '<Column ss:Width="'.$width.'"/>';
        }

        return $xml."\n";
    }

    /**
     * Excel worksheet names: max 31 chars, no : \ / ? * [ ] characters, and must be
     * unique within the workbook (Sheet, Sheet (2), ... on collision after truncation).
     *
     * @param  array<string, true>  &$usedNames
     */
    private static function uniqueSheetName(string $name, array &$usedNames): string
    {
        $clean = trim(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $name) ?? $name);
        $clean = $clean === '' ? 'Sheet' : mb_substr($clean, 0, 31);

        $candidate = $clean;
        $suffix = 2;
        while (isset($usedNames[$candidate])) {
            $candidate = mb_substr($clean, 0, 31 - strlen(" ({$suffix})"))." ({$suffix})";
            $suffix++;
        }

        $usedNames[$candidate] = true;

        return $candidate;
    }
}
