<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestPointRule extends Model
{
    protected $fillable = ['event_id', 'grade', 'position', 'points', 'is_group', 'points_table'];

    protected $casts = ['is_group' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
