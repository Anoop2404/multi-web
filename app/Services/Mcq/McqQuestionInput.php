<?php

namespace App\Services\Mcq;

use App\Models\McqQuestion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates one question (from the editor form or an XLSX row) and turns it into
 * McqQuestion attributes. Blank options are dropped and the rest re-lettered A, B, C…
 * so students never see gaps, with the answer key re-mapped to match.
 *
 * Question text and option labels are plain text that may contain any Unicode
 * (Malayalam, symbols) and LaTeX formulas between $…$ (inline) or $$…$$ (display),
 * rendered with KaTeX. Either may be left empty when an image is attached instead.
 *
 * Input shape:
 *   type             single | multiple | true_false | numeric
 *   body_text        question text
 *   options          list of {key, label, image_path?}  — single / multiple
 *                    (image_path is set by the controller from uploads, never taken from the client)
 *   correct_keys     list of option keys  — single / true_false (exactly one), multiple (one or more)
 *   show_count       multiple: tell students how many to pick (default true)
 *   answer_value     number               — numeric
 *   answer_tolerance number ≥ 0           — numeric, optional
 *   marks, negative_mark, explanation, difficulty, title
 */
class McqQuestionInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> McqQuestion attributes
     *
     * @throws ValidationException
     */
    public static function normalize(array $input, bool $hasImage = false): array
    {
        $validator = Validator::make($input, [
            'type'               => 'required|in:'.implode(',', McqQuestion::TYPES),
            'title'              => 'nullable|string|max:255',
            'body_text'          => 'nullable|string|max:5000',
            'options'            => 'nullable|array|max:6',
            'options.*.key'      => 'nullable|string|max:10',
            'options.*.label'    => 'nullable|string|max:1000',
            'options.*.image_path' => 'nullable|string|max:500',
            'correct_keys'       => 'nullable|array|max:6',
            'correct_keys.*'     => 'string|max:10',
            'show_count'         => 'nullable|boolean',
            'answer_value'       => 'nullable|numeric|between:-1000000000,1000000000',
            'answer_tolerance'   => 'nullable|numeric|min:0|max:1000000',
            'marks'              => 'nullable|numeric|min:0.01|max:1000',
            'negative_mark'      => 'nullable|numeric|min:0|max:1000',
            'explanation'        => 'nullable|string|max:5000',
            'difficulty'         => 'nullable|in:'.implode(',', McqQuestion::DIFFICULTIES),
        ], [], [
            'body_text'        => 'question text',
            'answer_value'     => 'answer',
            'answer_tolerance' => 'tolerance',
            'negative_mark'    => 'negative mark',
        ]);

        $validator->after(function ($validator) use ($input, $hasImage) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (trim((string) ($input['body_text'] ?? '')) === '' && ! $hasImage) {
                $validator->errors()->add('body_text', 'Enter the question text or attach an image.');
            }

            $type = $input['type'];
            if ($type === McqQuestion::TYPE_NUMERIC) {
                if (! is_numeric($input['answer_value'] ?? null)) {
                    $validator->errors()->add('answer_value', 'Enter the correct numeric answer.');
                }

                return;
            }

            [$options, $keyMap] = self::optionsFor($type, $input['options'] ?? []);
            if (count($options) < 2) {
                $validator->errors()->add('options', 'Add at least two options (text, formula or image).');

                return;
            }

            $correct = self::mapCorrectKeys($input['correct_keys'] ?? [], $keyMap);
            if (in_array($type, McqQuestion::ONE_OPTION_TYPES, true) && count($correct) !== 1) {
                $validator->errors()->add('correct_keys', $type === McqQuestion::TYPE_TRUE_FALSE
                    ? 'Choose whether the statement is True or False.'
                    : 'Pick exactly one correct option.');
            }
            if ($type === McqQuestion::TYPE_MULTIPLE && $correct === []) {
                $validator->errors()->add('correct_keys', 'Pick at least one correct option.');
            }
        });

        $data = $validator->validate();
        $type = $data['type'];

        $attributes = [
            'type'               => $type,
            'title'              => filled($data['title'] ?? null) ? trim($data['title']) : null,
            'body_text'          => filled($data['body_text'] ?? null) ? trim($data['body_text']) : null,
            'marks'              => isset($data['marks']) ? round((float) $data['marks'], 2) : 1,
            'negative_mark'      => isset($data['negative_mark']) ? round((float) $data['negative_mark'], 2) : 0,
            'explanation'        => filled($data['explanation'] ?? null) ? trim($data['explanation']) : null,
            'difficulty'         => $data['difficulty'] ?? null,
            'options_json'       => null,
            'correct_option_key' => null,
            'answer_json'        => null,
        ];

        if ($type === McqQuestion::TYPE_NUMERIC) {
            $attributes['answer_json'] = [
                'value'     => (float) $data['answer_value'],
                'tolerance' => isset($data['answer_tolerance']) ? (float) $data['answer_tolerance'] : 0.0,
            ];

            return $attributes;
        }

        [$options, $keyMap] = self::optionsFor($type, $data['options'] ?? []);
        $correct = self::mapCorrectKeys($data['correct_keys'] ?? [], $keyMap);
        $attributes['options_json'] = $options;

        if ($type === McqQuestion::TYPE_MULTIPLE) {
            $attributes['answer_json'] = ['keys' => $correct, 'show_count' => (bool) ($data['show_count'] ?? true)];
        } else {
            $attributes['correct_option_key'] = $correct[0];
        }

        return $attributes;
    }

    /**
     * True / False questions always get the two fixed options; other types keep the
     * non-blank options (a label, an image, or both), re-lettered a, b, c…
     *
     * @return array{0: list<array<string, string>>, 1: array<string, string>} options and old key → new key
     */
    private static function optionsFor(string $type, array $options): array
    {
        if ($type === McqQuestion::TYPE_TRUE_FALSE) {
            return [McqQuestion::TRUE_FALSE_OPTIONS, ['a' => 'a', 'b' => 'b', 'true' => 'a', 'false' => 'b', 't' => 'a', 'f' => 'b']];
        }

        $clean = [];
        $map = [];
        foreach (array_values($options) as $index => $option) {
            $label = trim((string) ($option['label'] ?? ''));
            $image = trim((string) ($option['image_path'] ?? ''));
            if (($label === '' && $image === '') || count($clean) >= count(McqQuestion::OPTION_KEYS)) {
                continue;
            }
            $newKey = McqQuestion::OPTION_KEYS[count($clean)];
            $oldKey = strtolower(trim((string) ($option['key'] ?? McqQuestion::OPTION_KEYS[$index] ?? '')));
            if ($oldKey !== '') {
                $map[$oldKey] = $newKey;
            }
            $clean[] = array_filter(['key' => $newKey, 'label' => $label, 'image_path' => $image ?: null], fn ($v) => $v !== null);
        }

        return [$clean, $map];
    }

    /** @return list<string> */
    private static function mapCorrectKeys(array $keys, array $keyMap): array
    {
        return collect($keys)
            ->map(fn ($key) => $keyMap[strtolower(trim((string) $key))] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The editor's view of a stored question (the inverse of normalize()). Option images are
     * reported as has_image flags; their storage paths never leave the server.
     *
     * @return array<string, mixed>
     */
    public static function toForm(McqQuestion $question): array
    {
        $numeric = $question->numericKey();

        return [
            'type'             => $question->typeValue(),
            'title'            => $question->title,
            'body_text'        => $question->body_text,
            'options'          => collect($question->options_json ?? [])
                ->map(fn ($o) => [
                    'key'       => strtolower((string) ($o['key'] ?? '')),
                    'label'     => (string) ($o['label'] ?? ''),
                    'has_image' => filled($o['image_path'] ?? null),
                ])
                ->values()
                ->all(),
            'correct_keys'     => $question->typeValue() === McqQuestion::TYPE_NUMERIC ? [] : $question->correctKeys(),
            'show_count'       => $question->typeValue() === McqQuestion::TYPE_MULTIPLE ? $question->showsCorrectCount() : true,
            'answer_value'     => $numeric['value'] ?? null,
            'answer_tolerance' => $numeric['tolerance'] ?? null,
            'marks'            => $question->marksValue(),
            'negative_mark'    => $question->negativeMarkValue(),
            'explanation'      => $question->explanation,
            'difficulty'       => $question->difficulty,
        ];
    }
}
