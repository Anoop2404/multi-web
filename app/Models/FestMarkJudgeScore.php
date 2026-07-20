<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestMarkJudgeScore extends Model
{
    protected $fillable = ['item_id', 'participant_id', 'judge_number', 'score'];

    protected $casts = [
        'score' => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }
}
