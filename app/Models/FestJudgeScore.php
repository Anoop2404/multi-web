<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestJudgeScore extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'participant_id', 'judge_user_id',
        'grade', 'score', 'measurement_value', 'measurement_unit', 'notes',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_user_id');
    }
}
