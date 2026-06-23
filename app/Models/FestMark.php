<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestMark extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'participant_id', 'grade', 'position',
        'score', 'ref_data_json', 'locked_by', 'locked_at',
    ];

    protected $casts = [
        'score'         => 'decimal:2',
        'ref_data_json' => 'array',
        'locked_at'     => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }
}
