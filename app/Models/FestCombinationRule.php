<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestCombinationRule extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'class_group',
        'max_arts_events', 'max_sports_events', 'max_common_events',
        'max_on_stage', 'max_off_stage', 'max_group',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
