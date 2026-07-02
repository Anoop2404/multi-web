<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestRecordBreak extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'athletic_record_id', 'participant_id', 'mark_id',
        'previous_value', 'new_value', 'record_unit', 'prize_label', 'prize_awarded', 'broken_at',
    ];

    protected $casts = [
        'previous_value' => 'decimal:4',
        'new_value'      => 'decimal:4',
        'prize_awarded'  => 'boolean',
        'broken_at'      => 'datetime',
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

    public function mark(): BelongsTo
    {
        return $this->belongsTo(FestMark::class, 'mark_id');
    }

    public function athleticRecord(): BelongsTo
    {
        return $this->belongsTo(FestAthleticRecord::class, 'athletic_record_id');
    }
}
