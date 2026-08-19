<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEventPhase extends Model
{
    protected $fillable = [
        'event_id',
        'source_phase_id',
        'registration_batch_id',
        'is_regional',
        'result_publish_mode',
        'name',
        'code',
        // §7.3 item 2 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): nullable
        // region-group namespace (e.g. 'off_stage', 'sargadhara'). Marks this phase as
        // "regional" and which SchoolRegionAssignment.partition_group it reads from.
        // NULL (the default for every existing phase) means "not a regional phase" —
        // see isRegional() below and FestRegionPartitionService/FestItemSyncService for
        // where this is consumed.
        'region_partition_group',
        'sort_order',
        'is_default',
        'school_registration_fee_share',
        'student_registration_fee',
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
        'is_regional' => 'boolean',
        'school_registration_fee_share' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
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

    /** The hub/root phase this one was cloned from, if this is a region-child phase. */
    public function sourcePhase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_phase_id');
    }

    /** Region-child phases cloned from this one, if this is a source/parent phase. */
    public function childPhases(): HasMany
    {
        return $this->hasMany(self::class, 'source_phase_id');
    }

    public function registrationBatch(): BelongsTo
    {
        return $this->belongsTo(FestRegistrationBatch::class, 'registration_batch_id');
    }

    public function allowedRegions(): HasMany
    {
        return $this->hasMany(FestPhaseRegion::class, 'phase_id');
    }

    public function regionSelections(): HasMany
    {
        return $this->hasMany(FestSchoolPhaseRegionSelection::class, 'phase_id');
    }

    /**
     * §7.3 item 2 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): true only when
     * region_partition_group is explicitly set. Every phase created before this column
     * existed (and every phase a Sahodaya never opts into multi-group regions with)
     * resolves to false here, unchanged from today's behavior.
     */
    public function isRegional(): bool
    {
        return (bool) $this->is_regional || filled($this->region_partition_group);
    }
}
