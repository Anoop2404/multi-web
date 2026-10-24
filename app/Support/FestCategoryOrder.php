<?php

namespace App\Support;

class FestCategoryOrder
{
    /** Sort visible keys by their display labels, rather than opaque category IDs. */
    public static function sort(array $keys, array $labels, array $config = []): array
    {
        $mode = $config['category_display_sort'] ?? 'natural';
        $positions = array_flip($config['category_display_order'] ?? []);
        $number = static function (string $label): int {
            if (preg_match('/(?:category|cat)\s*([ivxlcdm]+|\d+)\b/i', $label, $match)) {
                if (ctype_digit($match[1])) return (int) $match[1];
                $values = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
                $total = 0;
                $previous = 0;
                foreach (str_split(strrev(strtoupper($match[1]))) as $letter) {
                    $value = $values[$letter];
                    $total += $value < $previous ? -$value : $value;
                    $previous = max($previous, $value);
                }
                return $total;
            }
            return preg_match('/\d+/', $label, $match) ? (int) $match[0] : PHP_INT_MAX;
        };
        usort($keys, static function ($a, $b) use ($mode, $positions, $labels, $number) {
            if ($mode === 'custom') {
                $difference = ($positions[$a] ?? PHP_INT_MAX) <=> ($positions[$b] ?? PHP_INT_MAX);
                if ($difference) return $difference;
            }
            $first = $labels[$a] ?? $a;
            $second = $labels[$b] ?? $b;
            if ($mode !== 'name') {
                $difference = $number($first) <=> $number($second);
                if ($difference) return $difference;
            }
            return strnatcasecmp($first, $second) ?: strcmp($a, $b);
        });
        return $keys;
    }
}
