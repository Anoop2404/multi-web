<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FestCompetitionArea extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'parent_id', 'name', 'slug', 'sort_order', 'is_active',
        'reg_start', 'reg_end', 'competition_start', 'competition_end', 'competition_time',
        'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
        'included_items_per_student', 'included_teams', 'default_item_fee', 'extra_item_fee',
        'verification_policy', 'approval_policy', 'max_participants', 'max_teams', 'venue',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reg_start' => 'date',
        'reg_end' => 'date',
        'competition_start' => 'date',
        'competition_end' => 'date',
        'school_registration_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'team_registration_fee' => 'decimal:2',
        'default_item_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $area) {
            if (! filled($area->slug)) {
                $area->slug = Str::slug($area->name) ?: 'area';
            }
        });
    }

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
        return $this->hasMany(FestEventItem::class, 'area_id');
    }

    public function requiresVerifiedStudentsOnly(): bool
    {
        return $this->verification_policy === 'verified_only';
    }

    public function requiresManualApproval(): bool
    {
        return $this->approval_policy === 'manual';
    }
}
