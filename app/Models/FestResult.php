<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestResult extends Model
{
    protected $fillable = ["event_id", "item_id", "school_id", "total_points", "rank", "published_at", "published_by"];

    protected $casts = [
        'total_points' => 'decimal:2',
        'published_at' => 'datetime',
    ];
}
