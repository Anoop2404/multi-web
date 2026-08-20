<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestPhaseScoreSnapshot extends Model
{
    protected $fillable = [
        'root_event_id', 'phase_id', 'school_id', 'championship_category_key',
        'version', 'opening_points', 'current_points', 'closing_points', 'rank',
        'locked_at', 'locked_by', 'correction_reason', 'invalidated_at', 'invalidated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'opening_points' => 'decimal:2',
        'current_points' => 'decimal:2',
        'closing_points' => 'decimal:2',
        'rank' => 'integer',
        'locked_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];
}
