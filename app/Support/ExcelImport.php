<?php

namespace App\Support;

use ZipArchive;

class ExcelImport
{
    /**
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public static function associativeRows(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        [$headers, $matrix] = match ($ext) {
            'csv', 'txt' => self::parseCsv($path),
            'xls'          => self::parseSpreadsheetXml($path),
            'xlsx'         => self::parseXlsx($path),
            default        => self::parseCsv($path),
        };

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $rows = [];

        foreach ($matrix as $row) {
            $assoc = [];
            foreach ($headers as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = trim((string) ($row[$i] ?? ''));
            }

            if ($assoc !== []) {
                $rows[] = $assoc;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    private static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $header = fgetcsv($handle) ?: [];
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $row);
        }

        fclose($handle);

        return [$header, $rows];
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    private static function parseSpreadsheetXml(string $path): array
    {
        $xml = @file_get_contents($path);
        if ($xml === false || ! str_contains($xml, '<Workbook')) {
            return self::parseCsv($path);
        }

        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return [[], []];
        }

        $doc->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $rowNodes = $doc->xpath('//ss:Worksheet/ss:Table/ss:Row') ?: [];

        $matrix = [];
        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $colIndex = 0;
            foreach ($rowNode->Cell as $cell) {
                $index = isset($cell['Index']) ? (int) $cell['Index'] - 1 : $colIndex;
                while (count($cells) < $index) {
                    $cells[] = '';
                }
                $cells[$index] = trim((string) ($cell->Data ?? ''));
                $colIndex = $index + 1;
            }
            $matrix[] = $cells;
        }

        if ($matrix === []) {
            return [[], []];
        }

        $header = array_shift($matrix);

        return [$header, $matrix];
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    private static function parseXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [[], []];
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [[], []];
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sharedDoc = @simplexml_load_string($sharedXml);
            if ($sharedDoc) {
                foreach ($sharedDoc->si as $si) {
                    $shared[] = trim((string) ($si->t ?? collect($si->xpath('.//t'))->map(fn ($n) => (string) $n)->implode('')));
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: $zip->getFromName('xl/worksheets/Sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            return [[], []];
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [[], []];
        }

        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            $colIndex = 0;
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $index = $ref !== '' ? self::columnIndexFromCellRef($ref) : $colIndex;
                while (count($cells) <= $index) {
                    $cells[] = '';
                }

                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');
                if ($type === 's' && isset($shared[(int) $value])) {
                    $value = $shared[(int) $value];
                }

                $cells[$index] = trim($value);
                $colIndex = $index + 1;
            }
            $matrix[] = $cells;
        }

        if ($matrix === []) {
            return [[], []];
        }

        return [array_shift($matrix), $matrix];
    }

    private static function columnIndexFromCellRef(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return 0;
        }

        $letters = $m[1];
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }
}
