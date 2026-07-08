<?php

namespace App\Services\Migration;

use RuntimeException;

class LegacySqlInsertParser
{
    /**
     * @return list<array<string, string|null>>
     */
    public function parseTable(string $sql, string $table): array
    {
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'` \(([^)]+)\) VALUES\s*(.*?);\s*(?:--|\n\n|INSERT|CREATE|ALTER|$)/s';

        if (! preg_match($pattern, $sql, $matches)) {
            return [];
        }

        $columns = array_map(
            static fn (string $column) => trim($column, " `\t\n\r"),
            explode(',', $matches[1]),
        );

        return $this->parseValueRows($columns, trim($matches[2]));
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string|null>>
     */
    private function parseValueRows(array $columns, string $blob): array
    {
        $rows = [];
        $length = strlen($blob);
        $index = 0;

        while ($index < $length) {
            if ($blob[$index] !== '(') {
                $index++;

                continue;
            }

            $index++;
            $values = [];
            $current = '';
            $inString = false;

            while ($index < $length) {
                $char = $blob[$index];

                if ($inString) {
                    if ($char === "'") {
                        if ($index + 1 < $length && $blob[$index + 1] === "'") {
                            $current .= "'";
                            $index++;
                        } else {
                            $inString = false;
                        }
                    } else {
                        $current .= $char;
                    }
                } elseif ($char === "'") {
                    $inString = true;
                } elseif ($char === ',') {
                    $values[] = trim($current);
                    $current = '';
                } elseif ($char === ')') {
                    $values[] = trim($current);
                    $index++;
                    break;
                } elseif (! in_array($char, ["\t", "\n", "\r", ' '], true)) {
                    $current .= $char;
                }

                $index++;
            }

            if (count($values) !== count($columns)) {
                throw new RuntimeException(sprintf(
                    'Legacy SQL row for table has %d values but %d columns.',
                    count($values),
                    count($columns),
                ));
            }

            $row = [];
            foreach ($columns as $offset => $column) {
                $value = $values[$offset];
                $row[$column] = strtoupper($value) === 'NULL' ? null : $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
