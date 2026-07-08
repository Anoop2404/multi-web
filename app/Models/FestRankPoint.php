<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestRankPoint extends Model
{
    protected $fillable = ['event_id', 'rank', 'points', 'is_group'];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
