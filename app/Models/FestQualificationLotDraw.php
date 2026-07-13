<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestQualificationLotDraw extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'from_event_id', 'cutoff_position',
        'contested_participant_ids', 'selected_participant_ids',
        'method', 'seed', 'drawn_by', 'drawn_at', 'notes',
    ];

    protected $casts = [
        'contested_participant_ids' => 'array',
        'selected_participant_ids' => 'array',
        'drawn_at' => 'datetime',
    ];
}
