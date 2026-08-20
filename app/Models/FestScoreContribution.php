<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestScoreContribution extends Model
{
    protected $fillable = [
        'root_event_id', 'phase_id', 'source_event_id', 'school_id',
        'source_category_key', 'championship_category_key', 'version', 'points',
        'invalidated_at', 'invalidated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'points' => 'decimal:2',
        'invalidated_at' => 'datetime',
    ];
}
