<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = ["program_id", "title", "scheduled_at", "venue", "duration_minutes"];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'session_id');
    }
}
