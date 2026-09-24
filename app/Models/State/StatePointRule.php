<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * What a placing is worth. A null grade or position means "any", so the manual's "any position at
 * grade A" row is one row rather than one per placing.
 */
class StatePointRule extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_point_rules';

    protected $fillable = ['state_event_id', 'state_id', 'grade', 'position', 'is_group', 'points'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_group' => 'boolean', 'points' => 'integer'];
    }

    /**
     * How specific this rule is, for choosing between rules that all match. Grade and position each
     * count, so "A, 1st" beats "A, any" beats "any, 1st" — the same precedence the tenant side uses,
     * stated here rather than left to row order.
     */
    public function specificity(): int
    {
        return ($this->grade !== null ? 2 : 0) + ($this->position !== null ? 1 : 0);
    }

    public function matches(?string $grade, ?int $position, bool $isGroup): bool
    {
        return $this->is_group === $isGroup
            && ($this->grade === null || strcasecmp($this->grade, (string) $grade) === 0)
            && ($this->position === null || $this->position === $position);
    }
}
