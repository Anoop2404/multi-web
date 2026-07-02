<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestVenue extends Model
{
    protected $fillable = ['tenant_id', 'event_id', 'name', 'location', 'capacity', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FestStage::class, 'venue_id');
    }
}
