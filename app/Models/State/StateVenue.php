<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place at a State event: a venue, a stage inside it, a room, a green room or a reporting desk.
 * One table rather than several because the schedule only ever asks "which place", and they differ
 * by what they are for, not by what they hold.
 */
class StateVenue extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_venues';

    public const KINDS = ['venue', 'stage', 'room', 'green_room', 'reporting'];

    protected $fillable = [
        'state_event_id', 'state_id', 'parent_id', 'name', 'code', 'kind', 'capacity',
        'address', 'directions', 'officer_name', 'officer_phone', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Only a stage or a room can hold a scheduled item; a venue is the building around them. */
    public function canHostItems(): bool
    {
        return in_array($this->kind, ['stage', 'room'], true);
    }
}
