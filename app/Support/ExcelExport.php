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
        $escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
        $xml .= '<Worksheet ss:Name="Sheet1"><Table>'."\n";

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell><Data ss:Type="String">'.$escape($header).'</Data></Cell>';
        }
        $xml .= '</Row>'."\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $type = is_numeric($cell) && $cell !== '' && $cell !== null ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="'.$type.'">'.$escape($cell).'</Data></Cell>';
            }
            $xml .= '</Row>'."\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }
}
