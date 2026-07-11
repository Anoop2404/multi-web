<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = [
        'program_id',
        'title',
        'scheduled_at',
        'venue',
        'duration_minutes',
        'attendance_token',
        'resource_person_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (! filled($session->attendance_token)) {
                $session->attendance_token = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(40));
            }
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function resourcePerson(): BelongsTo
    {
        return $this->belongsTo(TrainingResourcePerson::class, 'resource_person_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'session_id');
    }
}
