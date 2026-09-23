<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One volunteer or official, on one day, in one session, at one place. */
class StateStaffDuty extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_staff_duties';

    public const SESSIONS = ['morning', 'afternoon', 'evening', 'full_day'];

    protected $fillable = [
        'state_event_id', 'state_id', 'staff_id', 'duty_on', 'session', 'venue_id', 'duty', 'notes',
    ];

    protected function casts(): array
    {
        return ['duty_on' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StateEventStaff::class, 'staff_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(StateVenue::class, 'venue_id');
    }
}
