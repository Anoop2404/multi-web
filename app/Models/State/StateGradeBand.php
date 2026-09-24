<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** One grade band for a State event: "A is 70 to 100". Item-specific when item_id is set. */
class StateGradeBand extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_grade_bands';

    protected $fillable = [
        'state_event_id', 'state_id', 'item_id', 'grade', 'min_score', 'max_score', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['min_score' => 'decimal:2', 'max_score' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function contains(float $score): bool
    {
        return $score >= (float) $this->min_score && $score <= (float) $this->max_score;
    }
}
