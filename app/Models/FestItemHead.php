<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestItemHead extends Model
{
    /** Attendance/verification-gate-style policy constants for sports_composite billing. */
    public const VERIFICATION_POLICIES = ['verified_only', 'all_students'];

    public const APPROVAL_POLICIES = ['auto', 'manual'];

    protected $fillable = [
        'tenant_id', 'event_id', 'event_type', 'parent_id', 'name', 'slug',
        'sport_discipline', 'catalog_key', 'is_team_heading', 'sort_order',
        'default_item_fee', 'extra_item_fee',
        'reg_start', 'reg_end', 'competition_start', 'competition_end',
        'schedule_mode', 'competition_time',
        'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
        'included_items_per_student', 'included_teams',
        'verification_policy', 'approval_policy',
        'max_participants', 'max_teams',
    ];

    protected $casts = [
        'is_team_heading' => 'boolean',
        'default_item_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
        'reg_start' => 'date',
        'reg_end' => 'date',
        'competition_start' => 'date',
        'competition_end' => 'date',
        'school_registration_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'team_registration_fee' => 'decimal:2',
        'included_items_per_student' => 'integer',
        'included_teams' => 'integer',
        'max_participants' => 'integer',
        'max_teams' => 'integer',
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

    /** Whether this head's approval policy requires manual Sahodaya review rather than auto-approval. */
    public function requiresManualApproval(): bool
    {
        return $this->approval_policy === 'manual';
    }

    /** Whether only school-verified students may register under this head. */
    public function requiresVerifiedStudentsOnly(): bool
    {
        return $this->verification_policy === 'verified_only';
    }
}
