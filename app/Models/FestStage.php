<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestStage extends Model
{
    protected $fillable = [
        'event_id', 'venue_id', 'name', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(FestVenue::class, 'venue_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FestSchedule::class, 'stage_id');
    }
}
