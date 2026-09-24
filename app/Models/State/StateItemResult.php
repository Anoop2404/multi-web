<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * The result state of one item. Per item rather than per event, because a Kalotsavam publishes over
 * several days and an item under appeal must be held back without freezing every other item.
 */
class StateItemResult extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_item_results';

    public const DRAFT = 'draft';

    /** Computed and visible to the office, but not to the public. */
    public const PROVISIONAL = 'provisional';

    public const PUBLISHED = 'published';

    /** Final: refuses recomputation. */
    public const LOCKED = 'locked';

    protected $fillable = [
        'state_event_id', 'state_id', 'item_id', 'item_code', 'status',
        'computed_at', 'published_at', 'locked_at', 'ranked_count', 'tie_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'computed_at'  => 'datetime',
            'published_at' => 'datetime',
            'locked_at'    => 'datetime',
        ];
    }

    public function isPublic(): bool
    {
        return in_array($this->status, [self::PUBLISHED, self::LOCKED], true);
    }

    public function isLocked(): bool
    {
        return $this->status === self::LOCKED;
    }
}
