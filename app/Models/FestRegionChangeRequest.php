<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestRegionChangeRequest extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'phase_id', 'school_id', 'current_region_id', 'requested_region_id',
        'reason', 'status', 'resolution_note', 'requested_by_user_id',
        'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'phase_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function currentRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'current_region_id');
    }

    public function requestedRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'requested_region_id');
    }
}
