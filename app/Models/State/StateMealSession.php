<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One sitting at one venue on one day — "lunch, 12 Jan, main hall". */
class StateMealSession extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_meal_sessions';

    /** Suggested, not enforced: the column is free text so an event can add its own. */
    public const SESSIONS = ['breakfast', 'lunch', 'dinner', 'refreshment'];

    protected $fillable = [
        'state_event_id', 'state_id', 'served_on', 'session', 'menu', 'venue_id', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return ['served_on' => 'date', 'is_active' => 'boolean', 'capacity' => 'integer'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StateMealAllocation::class, 'meal_session_id');
    }
}
