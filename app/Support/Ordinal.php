<?php

namespace App\Support;

/**
 * "1st", "2nd", "3rd" — for certificates and printed sheets, where "Position 1" reads as a database
 * column and "First place" reads as an award.
 */
class Ordinal
{
    public static function of(int|string|null $number): string
    {
        if ($number === null || $number === '' || ! is_numeric($number)) {
            return (string) $number;
        }

        $n = (int) $number;

        // 11th, 12th, 13th — the one place the last-digit rule is wrong.
        $suffix = in_array($n % 100, [11, 12, 13], true)
            ? 'th'
            : match ($n % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };

        return $n.$suffix;
    }
}
