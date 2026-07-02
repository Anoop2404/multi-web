<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircularAcknowledgement extends Model
{
    protected $fillable = ["circular_id", "user_id", "school_id", "acknowledged_at"];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];
}
