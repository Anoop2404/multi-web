<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateAttendance extends StateModel
{
    protected $table = 'state_attendances';

    protected $fillable = [
        'state_event_id', 'item_id', 'item_code', 'registration_id', 'participant_id',
        'status', 'marked_by', 'marked_at',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function stateEvent(): BelongsTo
    {
        return $this->belongsTo(StateFestEvent::class, 'state_event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StateFestRegistration::class, 'registration_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(StateFestParticipant::class, 'participant_id');
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }
}
