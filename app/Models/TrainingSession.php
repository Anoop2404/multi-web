<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    protected $fillable = ["program_id", "title", "scheduled_at", "venue", "duration_minutes"];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];
}
