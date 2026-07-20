<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestMarkCriterionScore extends Model
{
    protected $fillable = ['criterion_id', 'item_id', 'participant_id', 'score'];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(FestMarkCriterion::class, 'criterion_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'participant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }
}
