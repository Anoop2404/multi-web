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
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
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
