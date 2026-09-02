<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return response()->streamDownload(
            fn () => print(self::spreadsheetXml($headers, $rows)),
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
     */
    public static function spreadsheetXml(array $headers, iterable $rows): string
    {
        return self::workbookXml(['Sheet1' => ['headers' => $headers, 'rows' => $rows]]);
    }

    /**
     * Multi-sheet workbook — one tab per entry in $sheets, in the order given.
     *
     * @param  array<string, array{headers: list<string>, rows: iterable<int, list<string|int|float|null>>}>  $sheets  sheet name => {headers, rows}
     */
    public static function downloadMultiSheet(string $filename, array $sheets): StreamedResponse
    {
        if (! str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        return response()->streamDownload(
            fn () => print(self::workbookXml($sheets)),
            $filename,
            [
                'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    /**
     * @param  array<string, array{headers: list<string>, rows: iterable<int, list<string|int|float|null>>}>  $sheets
     */
    private static function workbookXml(array $sheets): string
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
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F172A" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="body"><Alignment ss:Vertical="Center"/></Style>';
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

            $xml .= '<Worksheet ss:Name="'.$escape($safeName).'">';
            $xml .= '<Table>'."\n";
            $xml .= self::columnWidthsXml($sheet['headers'], $rows);

            $xml .= '<Row ss:StyleID="header">';
            foreach ($sheet['headers'] as $header) {
                $xml .= '<Cell><Data ss:Type="String">'.$escape($header).'</Data></Cell>';
            }
            $xml .= '</Row>'."\n";

            foreach ($rows as $row) {
                $xml .= '<Row ss:StyleID="body">';
                foreach ($row as $cell) {
                    $type = is_numeric($cell) && $cell !== '' && $cell !== null ? 'Number' : 'String';
                    $xml .= '<Cell><Data ss:Type="'.$type.'">'.$escape($cell).'</Data></Cell>';
                }
                $xml .= '</Row>'."\n";
            }

            $xml .= '</Table>';
            $xml .= '</Worksheet>'."\n";
        }

        $xml .= '</Workbook>';

        return $xml;
    }

    /**
     * Column widths sized to the widest of the header or any cell in that column
     * (sampled across every row — exports here run to at most a few thousand rows, so a
     * full scan is cheap), clamped to a readable range. SpreadsheetML ss:Width is in
     * points; ~6.5pt per character is a reasonable approximation for the default font.
     *
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function columnWidthsXml(array $headers, array $rows): string
    {
        $maxLen = [];
        foreach ($headers as $i => $header) {
            $maxLen[$i] = mb_strlen((string) $header);
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
