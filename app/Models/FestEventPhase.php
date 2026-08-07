<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEventPhase extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'code',
        'sort_order',
        'is_default',
        'starts_at',
        'ends_at',
        'registration_open',
        'registration_close',
        'registration_locked',
        'food_cutoff_at',
        'status',
        'scoring_locked',
        'schedule_published',
        'results_published',
        'appeals_open',
        'appeal_deadline_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_open' => 'datetime',
        'registration_close' => 'datetime',
        'registration_locked' => 'boolean',
        'food_cutoff_at' => 'datetime',
        'scoring_locked' => 'boolean',
        'schedule_published' => 'boolean',
        'results_published' => 'boolean',
        'appeals_open' => 'boolean',
        'appeal_deadline_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestEventItem::class, 'phase_id');
    }
}
