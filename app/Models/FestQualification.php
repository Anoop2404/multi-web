<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestQualification extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'participant_id',
        'next_level_event_id', 'promoted_at',
    ];

    protected $casts = ['promoted_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function nextLevelEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'next_level_event_id');
    }
}
