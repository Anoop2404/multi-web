<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestAttendance extends Model
{
    protected $table = 'fest_attendance';

    protected $fillable = ["event_id", "item_id", "participant_id", "status", "marked_by", "marked_at", "corrected_by", "corrected_at"];

    protected $casts = [
        'marked_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];
}
