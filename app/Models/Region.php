<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Region extends Model
{
    protected $fillable = [
        'tenant_id', 'fest_event_id', 'name', 'code', 'description', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(SchoolRegionAssignment::class, 'region_id');
    }

    public function festEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'fest_event_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Sahodaya-wide regions only -- excludes rows scoped to one FestEvent's Phases. This is
     * the default everywhere except the handful of call sites that explicitly need
     * event-scoped regions too (visibleToEvent()); those call sites opt in deliberately.
     */
    public function scopeGlobalOnly($query)
    {
        return $query->whereNull('fest_event_id');
    }

    /** Sahodaya-wide regions plus any region scoped to this specific event. */
    public function scopeVisibleToEvent($query, int $eventId)
    {
        return $query->where(fn ($q) => $q->whereNull('fest_event_id')->orWhere('fest_event_id', $eventId));
    }

    /**
     * Auto-suffix-on-collision code generator shared by the global region-creation path
     * (RegionController::store/update) and event-scoped region creation
     * (FestEventPhaseController::storeRegion). Uniqueness is checked within the same scope
     * the new row will occupy: sahodaya-wide when $festEventId is null, that one event's
     * scope otherwise (matches the regions table's (tenant_id, fest_event_id, code) unique
     * index).
     */
    public static function generateUniqueCode(string $tenantId, string $desired, ?int $festEventId = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($desired) ?: 'region';
        $candidate = $base;
        $i = 1;

        while (
            static::query()
                ->where('tenant_id', $tenantId)
                ->where('fest_event_id', $festEventId)
                ->where('code', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.(++$i);
        }

        return $candidate;
    }
}
