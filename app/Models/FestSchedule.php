<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchedule extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'participant_id',
        'scheduled_at', 'stage', 'stage_id', 'sort_order', 'called_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'called_at'    => 'datetime',
    ];

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

    public function festStage(): BelongsTo
    {
        return $this->belongsTo(FestStage::class, 'stage_id');
    }
}
