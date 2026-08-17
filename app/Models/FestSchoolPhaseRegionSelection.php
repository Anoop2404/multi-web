<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolPhaseRegionSelection extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'phase_id', 'school_id', 'region_id', 'selected_at',
        'selected_by', 'locked_at', 'changed_at', 'changed_by', 'change_reason',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
        'locked_at' => 'datetime',
        'changed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'phase_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
