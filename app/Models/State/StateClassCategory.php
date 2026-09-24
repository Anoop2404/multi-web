<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * A class category for a State event — "Category 2, classes 5 to 7".
 *
 * The code matches FestStateProgramItem.class_group, which the State Kalotsav items are already
 * seeded with (category_1 … category_5).
 */
class StateClassCategory extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_class_categories';

    protected $fillable = [
        'state_event_id', 'state_id', 'code', 'label', 'min_class', 'max_class', 'is_open', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_class' => 'integer', 'max_class' => 'integer',
            'is_open' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    /** An open category admits any class; a bounded one admits only its range. */
    public function admits(?int $classNumber): bool
    {
        if ($this->is_open || ($this->min_class === null && $this->max_class === null)) {
            return true;
        }

        // An unknown class is not admitted here — the caller decides whether that is a refusal or a
        // warning, because "we do not know this child's class" and "this child is the wrong age" are
        // different problems.
        if ($classNumber === null) {
            return false;
        }

        return $classNumber >= ($this->min_class ?? 1) && $classNumber <= ($this->max_class ?? 12);
    }

    public function range(): string
    {
        if ($this->is_open || ($this->min_class === null && $this->max_class === null)) {
            return 'Any class';
        }

        return $this->min_class === $this->max_class
            ? "Class {$this->min_class}"
            : "Classes {$this->min_class}–{$this->max_class}";
    }
}
