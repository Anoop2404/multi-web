<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestPhaseAdvancement extends Model
{
    protected $fillable = [
        'root_event_id',
        'from_phase_id',
        'to_phase_id',
        'from_item_id',
        'to_item_id',
        'from_registration_id',
        'target_registration_id',
        'region_id',
        'advanced_by',
        'advanced_at',
        'withdrawn_at',
        'withdrawn_by',
    ];

    protected $casts = [
        'advanced_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function rootEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'root_event_id');
    }

    public function fromPhase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'from_phase_id');
    }

    public function toPhase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'to_phase_id');
    }

    public function fromItem(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'from_item_id');
    }

    public function toItem(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'to_item_id');
    }

    public function fromRegistration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'from_registration_id');
    }

    public function targetRegistration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'target_registration_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }
}
