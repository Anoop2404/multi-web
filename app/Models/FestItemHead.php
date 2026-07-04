<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestItemHead extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'event_type', 'parent_id', 'name', 'slug',
        'sport_discipline', 'catalog_key', 'is_team_heading', 'sort_order',
    ];

    protected $casts = [
        'is_team_heading' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestEventItem::class, 'head_id')->orderBy('display_order');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(FestEventStaff::class, 'head_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent($query, ?int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
