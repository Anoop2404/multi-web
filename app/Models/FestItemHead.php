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
        'default_item_fee', 'extra_item_fee',
        'reg_start', 'reg_end', 'competition_start', 'competition_end',
        'schedule_mode', 'competition_time',
    ];

    protected $casts = [
        'is_team_heading' => 'boolean',
        'default_item_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
        'reg_start' => 'date',
        'reg_end' => 'date',
        'competition_start' => 'date',
        'competition_end' => 'date',
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

    /** Whether all items under this head are conducted together at one date+time. */
    public function isSameTime(): bool
    {
        return $this->schedule_mode === 'same_time';
    }

    /** 'HH:MM' time-of-day, or null. Postgres returns 'HH:MM:SS'. */
    public function competitionTimeShort(): ?string
    {
        return $this->competition_time ? substr((string) $this->competition_time, 0, 5) : null;
    }
}
