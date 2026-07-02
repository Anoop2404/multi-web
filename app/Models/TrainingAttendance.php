<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingAttendance extends Model
{
    protected $table = 'training_attendance';

    protected $fillable = ["session_id", "registration_id", "status", "marked_by", "marked_at"];

    protected $casts = [
        'marked_at' => 'datetime',
    ];
}
