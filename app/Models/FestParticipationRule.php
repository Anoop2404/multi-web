<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestParticipationRule extends Model
{
    protected $fillable = [
        'event_id', 'class_group',
        'max_total_events', 'max_onstage', 'max_offstage',
        'max_group_events', 'max_individual_events', 'max_events_per_student',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
