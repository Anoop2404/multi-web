<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestRankPoint extends Model
{
    protected $fillable = ['event_id', 'template_id', 'rank', 'points'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FestRankPointTemplate::class, 'template_id');
    }
}
