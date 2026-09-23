<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one Sahodaya is entitled to at one sitting, and what it was actually handed.
 *
 * The two counts are kept apart deliberately — see the migration.
 */
class StateMealAllocation extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_meal_allocations';

    protected $fillable = [
        'meal_session_id', 'state_event_id', 'sahodaya_id', 'sahodaya_name',
        'entitled_count', 'issued_count', 'notes', 'issued_by_user_id', 'issued_by_name', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'entitled_count' => 'integer',
            'issued_count' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StateMealSession::class, 'meal_session_id');
    }

    /** Positive when more was handed out than entitled — the figure that gets queried. */
    public function variance(): int
    {
        return $this->issued_count - $this->entitled_count;
    }
}
