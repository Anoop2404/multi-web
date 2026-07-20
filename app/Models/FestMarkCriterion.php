<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestMarkCriterion extends Model
{
    protected $fillable = ['event_id', 'item_id', 'label', 'max_score', 'sort_order'];

    protected $casts = [
        'max_score' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(FestMarkCriterionScore::class, 'criterion_id');
    }
}
