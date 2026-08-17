<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestPhaseRegion extends Model
{
    protected $fillable = [
        'phase_id', 'region_id', 'venue', 'conduct_start_at', 'conduct_end_at',
        'capacity', 'enabled',
    ];

    protected $casts = [
        'conduct_start_at' => 'datetime',
        'conduct_end_at' => 'datetime',
        'capacity' => 'integer',
        'enabled' => 'boolean',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'phase_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
}
