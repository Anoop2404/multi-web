<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestClashRequest extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'participant_id', 'schedule_id_a', 'schedule_id_b',
        'description', 'requested_resolution', 'status', 'resolution_note',
        'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function scheduleA(): BelongsTo
    {
        return $this->belongsTo(FestSchedule::class, 'schedule_id_a');
    }

    public function scheduleB(): BelongsTo
    {
        return $this->belongsTo(FestSchedule::class, 'schedule_id_b');
    }
}
