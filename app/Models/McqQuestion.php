<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqQuestion extends Model
{
    public const TYPE_SINGLE = 'single';
    public const TYPE_MULTIPLE = 'multiple';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_NUMERIC = 'numeric';

    public const TYPES = [self::TYPE_SINGLE, self::TYPE_MULTIPLE, self::TYPE_TRUE_FALSE, self::TYPE_NUMERIC];

    public const TYPE_LABELS = [
        self::TYPE_SINGLE     => 'Single correct',
        self::TYPE_MULTIPLE   => 'Multiple correct',
        self::TYPE_TRUE_FALSE => 'True / False',
        self::TYPE_NUMERIC    => 'Numeric answer',
    ];

    /** Types answered by picking exactly one option (graded identically). */
    public const ONE_OPTION_TYPES = [self::TYPE_SINGLE, self::TYPE_TRUE_FALSE];

    /** The fixed options of a True / False question. */
    public const TRUE_FALSE_OPTIONS = [
        ['key' => 'a', 'label' => 'True'],
        ['key' => 'b', 'label' => 'False'],
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    /** Option keys in the order the editor and the XLSX template use them. */
    public const OPTION_KEYS = ['a', 'b', 'c', 'd', 'e', 'f'];

    protected $fillable = [
        'bank_id', 'type', 'title', 'body_text', 'document_path', 'display_order', 'created_by_user_id',
        'options_json', 'correct_option_key', 'answer_json', 'marks', 'negative_mark',
        'explanation', 'difficulty',
    ];

    protected $casts = [
        'options_json'  => 'array',
        'answer_json'   => 'array',
        'marks'         => 'decimal:2',
        'negative_mark' => 'decimal:2',
    ];

    public function typeValue(): string
    {
        return in_array($this->type, self::TYPES, true) ? $this->type : self::TYPE_SINGLE;
    }

    /** Positive marks awarded for a correct answer (default 1). */
    public function marksValue(): float
    {
        $marks = (float) ($this->marks ?? 1);

        return $marks > 0 ? $marks : 1.0;
    }

    /** Marks deducted for an incorrect (answered) attempt (default 0). */
    public function negativeMarkValue(): float
    {
        return max(0.0, (float) ($this->negative_mark ?? 0));
    }

    /** @return list<string> option keys present on this question */
    public function optionKeys(): array
    {
        return collect($this->options_json ?? [])
            ->pluck('key')
            ->filter(fn ($key) => filled($key))
            ->map(fn ($key) => strtolower((string) $key))
            ->values()
            ->all();
    }

    /** @return list<string> the correct option keys (single / true-false: one key, multiple: one or more) */
    public function correctKeys(): array
    {
        if ($this->typeValue() === self::TYPE_MULTIPLE) {
            return collect($this->answer_json['keys'] ?? [])
                ->map(fn ($key) => strtolower((string) $key))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return filled($this->correct_option_key) ? [strtolower((string) $this->correct_option_key)] : [];
    }

    /** Whether students are told how many options to pick on a multiple-correct question ("Choose 2"). */
    public function showsCorrectCount(): bool
    {
        return $this->typeValue() === self::TYPE_MULTIPLE && (bool) ($this->answer_json['show_count'] ?? true);
    }

    /** @return list<string> stored image paths of the question and its options */
    public function imagePaths(): array
    {
        return collect($this->options_json ?? [])
            ->pluck('image_path')
            ->prepend($this->document_path)
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{value: float, tolerance: float}|null */
    public function numericKey(): ?array
    {
        if ($this->typeValue() !== self::TYPE_NUMERIC || ! is_numeric($this->answer_json['value'] ?? null)) {
            return null;
        }

        return [
            'value'     => (float) $this->answer_json['value'],
            'tolerance' => max(0.0, (float) ($this->answer_json['tolerance'] ?? 0)),
        ];
    }

    /** True when the question has everything needed to be marked automatically. */
    public function isGradable(): bool
    {
        return match ($this->typeValue()) {
            self::TYPE_NUMERIC  => $this->numericKey() !== null,
            self::TYPE_MULTIPLE => count($this->optionKeys()) >= 2
                && $this->correctKeys() !== []
                && array_diff($this->correctKeys(), $this->optionKeys()) === [],
            default => count($this->optionKeys()) >= 2
                && $this->correctKeys() !== []
                && in_array($this->correctKeys()[0], $this->optionKeys(), true),
        };
    }

    /** Human-readable answer key, e.g. "B", "A, C" or "12.5 (±0.1)". */
    public function answerKeyLabel(): string
    {
        if ($this->typeValue() === self::TYPE_NUMERIC) {
            $key = $this->numericKey();
            if (! $key) {
                return '—';
            }
            $value = rtrim(rtrim(number_format($key['value'], 6, '.', ''), '0'), '.');

            return $key['tolerance'] > 0
                ? $value.' (±'.rtrim(rtrim(number_format($key['tolerance'], 6, '.', ''), '0'), '.').')'
                : $value;
        }

        $keys = $this->correctKeys();

        return $keys ? strtoupper(implode(', ', $keys)) : '—';
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(McqQuestionBank::class, 'bank_id');
    }
}
