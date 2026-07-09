<?php

namespace App\Services\Spreadsheet;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads tabular data from either a CSV or an XLSX file, exposing both as a
 * uniform stream of rows so importers don't need to care which one was
 * uploaded. Format is detected by sniffing the file's content (XLSX files
 * are ZIP archives) rather than trusting the filename/extension, since
 * uploaded temp files rarely carry one.
 */
class SpreadsheetReader
{
    /** @return iterable<int, list<string|null>> */
    public static function rows(string $path): iterable
    {
        if ($path === '' || ! is_readable($path)) {
            return;
        }

        if (self::isXlsx($path)) {
            yield from self::xlsxRows($path);

            return;
        }

        yield from self::csvRows($path);
    }

    public static function isXlsx(string $path): bool
    {
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 4);
        fclose($handle);

        // XLSX files are ZIP archives, which always start with this signature.
        return $signature === "PK\x03\x04";
    }

    /** @return iterable<int, list<string|null>> */
    private static function xlsxRows(string $path): iterable
    {
        $reader = new XlsxReader;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map(self::castCell(...), $row->toArray());
                }

                break; // only the first sheet
            }
        } finally {
            $reader->close();
        }
    }

    /** @return iterable<int, list<string|null>> */
    private static function csvRows(string $path): iterable
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        // Skip a UTF-8 BOM if present, without corrupting binary-safe reads.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    private static function castCell(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) => rtrim(rtrim(sprintf('%.10f', $value), '0'), '.'),
            default => (string) $value,
        };
    }
}
