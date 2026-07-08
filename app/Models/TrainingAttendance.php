<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAttendance extends Model
{
    protected $table = 'training_attendance';

    protected $fillable = ["session_id", "registration_id", "status", "marked_by", "marked_at"];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TrainingRegistration::class, 'registration_id');
    }
}
