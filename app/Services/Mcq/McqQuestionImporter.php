<?php

namespace App\Services\Mcq;

use App\Models\McqQuestion;
use App\Services\Spreadsheet\SpreadsheetReader;
use Illuminate\Validation\ValidationException;

/**
 * Reads questions from an XLSX/CSV file laid out like template(). Text can hold any Unicode and
 * $…$ formulas; images are added afterwards in the editor. Each row is checked with
 * McqQuestionInput, so an imported question is exactly what the editor would have saved.
 * Nothing is written here: preview() returns per-row results and the valid attributes,
 * and the caller saves them after the admin confirms.
 */
class McqQuestionImporter
{
    public const MAX_ROWS = 1000;

    private const HEADERS = [
        'type', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f',
        'answer', 'tolerance', 'marks', 'negative_mark', 'difficulty', 'explanation',
    ];

    private const EXAMPLES = [
        ['single', 'Which planet is known as the Red Planet?', 'Venus', 'Mars', 'Jupiter', 'Saturn', '', '', 'B', '', '1', '0', 'easy', 'Iron oxide makes its surface look red.'],
        ['multiple', 'Which of these are prime numbers?', '2', '4', '7', '9', '', '', 'A,C', '', '2', '0.5', 'medium', ''],
        ['true_false', 'Water boils at 100 °C at sea level.', '', '', '', '', '', '', 'True', '', '1', '0', 'easy', ''],
        ['numeric', 'If $x^2 = 49$ and $x > 0$, what is $x$?', '', '', '', '', '', '', '7', '0', '1', '0', 'easy', ''],
    ];

    private const TYPE_ALIASES = [
        'single' => 'single', 'single correct' => 'single', 'mcq' => 'single', 'one' => 'single',
        'multiple' => 'multiple', 'multiple correct' => 'multiple', 'multi' => 'multiple', 'msq' => 'multiple',
        'true_false' => 'true_false', 'true/false' => 'true_false', 'true false' => 'true_false', 'tf' => 'true_false', 'boolean' => 'true_false',
        'numeric' => 'numeric', 'number' => 'numeric', 'numerical' => 'numeric', 'integer' => 'numeric',
    ];

    /** @return list<list<string>> header row plus one example row per question type */
    public static function template(): array
    {
        return array_merge([self::HEADERS], self::EXAMPLES);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, valid: list<array<string, mixed>>, total: int, error_count: int}
     */
    public function preview(string $path): array
    {
        $rows = [];
        $valid = [];
        $columns = null;
        $line = 0;

        foreach (SpreadsheetReader::rows($path) as $cells) {
            $line++;
            $cells = array_map(fn ($cell) => trim((string) ($cell ?? '')), $cells);

            if ($columns === null) {
                $columns = $this->mapHeader($cells);
                if ($columns === null) {
                    throw ValidationException::withMessages([
                        'file' => 'The first row must be the template\'s header row (type, question, option_a … answer, marks). Download the template and copy your questions into it.',
                    ]);
                }

                continue;
            }

            if (implode('', $cells) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => 'A file can have at most '.self::MAX_ROWS.' questions. Split it into smaller files.',
                ]);
            }

            $get = fn (string $column) => $cells[$columns[$column] ?? -1] ?? '';
            $result = $this->parseRow($get);
            $rows[] = ['row' => $line] + $result['summary'];
            if ($result['attributes'] !== null) {
                $valid[] = $result['attributes'];
            }
        }

        if ($columns === null) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        return [
            'rows'        => $rows,
            'valid'       => $valid,
            'total'       => count($rows),
            'error_count' => count($rows) - count($valid),
        ];
    }

    /** @return array<string, int>|null column name → index, or null when this isn't a header row */
    private function mapHeader(array $cells): ?array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $name = str_replace([' ', '-'], '_', strtolower($cell));
            if (in_array($name, self::HEADERS, true) && ! isset($map[$name])) {
                $map[$name] = $index;
            }
        }

        return isset($map['type'], $map['question'], $map['answer']) ? $map : null;
    }

    /**
     * @param  callable(string): string  $get
     * @return array{summary: array<string, mixed>, attributes: ?array<string, mixed>}
     */
    private function parseRow(callable $get): array
    {
        $rawType = strtolower($get('type'));
        $type = self::TYPE_ALIASES[$rawType] ?? null;
        $summary = [
            'type'     => $type ?? $rawType,
            'question' => mb_strimwidth($get('question'), 0, 140, '…'),
            'answer'   => $get('answer'),
            'marks'    => $get('marks') !== '' ? $get('marks') : '1',
            'errors'   => [],
        ];

        if ($type === null) {
            $summary['errors'][] = 'Type must be single, multiple, true_false or numeric.';

            return ['summary' => $summary, 'attributes' => null];
        }

        if ($this->isTemplateExample($get)) {
            $summary['errors'][] = 'This is an example row from the template. Delete it before importing.';

            return ['summary' => $summary, 'attributes' => null];
        }

        $options = [];
        foreach (McqQuestion::OPTION_KEYS as $key) {
            $options[] = ['key' => $key, 'label' => $get('option_'.$key)];
        }

        $input = [
            'type'          => $type,
            'body_text'     => $get('question'),
            'marks'         => $get('marks') !== '' ? $get('marks') : null,
            'negative_mark' => $get('negative_mark') !== '' ? $get('negative_mark') : null,
            'difficulty'    => $get('difficulty') !== '' ? strtolower($get('difficulty')) : null,
            'explanation'   => $get('explanation') !== '' ? $get('explanation') : null,
        ];

        if ($type === McqQuestion::TYPE_NUMERIC) {
            $input['answer_value'] = $get('answer') !== '' ? $get('answer') : null;
            $input['answer_tolerance'] = $get('tolerance') !== '' ? $get('tolerance') : null;
        } else {
            $input['options'] = $options;
            $input['correct_keys'] = $this->answerKeys($get('answer'));
            $known = array_map(fn ($o) => $o['key'], array_filter($options, fn ($o) => $o['label'] !== ''));
            $unknown = array_diff($input['correct_keys'], $known);
            if ($type !== McqQuestion::TYPE_TRUE_FALSE && $unknown !== []) {
                $summary['errors'][] = 'Answer '.strtoupper(implode(', ', $unknown)).' points to an empty option.';
            }
        }

        try {
            $attributes = McqQuestionInput::normalize($input);
        } catch (ValidationException $e) {
            $summary['errors'] = array_values(array_unique(array_merge($summary['errors'], collect($e->errors())->flatten()->all())));

            return ['summary' => $summary, 'attributes' => null];
        }

        if ($summary['errors'] !== []) {
            return ['summary' => $summary, 'attributes' => null];
        }

        return ['summary' => $summary, 'attributes' => $attributes];
    }

    /** "B", "a, c", "A;C", "AC" or "A C" → ['a', 'c'] */
    private function answerKeys(string $answer): array
    {
        $answer = strtolower($answer);
        $parts = preg_split('/[\s,;\/|]+/', $answer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 1 && preg_match('/^[a-f]{2,6}$/', $parts[0])) {
            $parts = str_split($parts[0]);
        }

        return array_values(array_unique($parts));
    }

    private function isTemplateExample(callable $get): bool
    {
        foreach (self::EXAMPLES as $example) {
            if ($get('question') === $example[1]) {
                return true;
            }
        }

        return false;
    }
}
