<?php

namespace App\Models;

use App\Support\FestTeamSquadRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FestEventItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id', 'title', 'item_code', 'category', 'stage_type', 'venue_type',
        'competition_format', 'sport_discipline', 'ranking_direction', 'result_method', 'duration_minutes', 'criteria_json', 'total_marks',
        'participant_type', 'gender', 'class_group', 'age_group', 'kids_band',
        'max_per_school', 'min_group_size', 'max_group_size', 'qualify_count',
        'owner_level', 'state_program_item_id', 'inherited_from_item_id', 'display_order',
        'fee_amount', 'group_item_flat_fee', 'group_item_per_participant_rate', 'is_enabled', 'is_mandatory', 'head_id', 'phase_id', 'area_id', 'reg_start', 'reg_end',
        'competition_start', 'competition_end', 'competition_time',
        'results_published_at', 'results_hidden', 'item_reg_id_start', 'chest_no_start',
        'quota_eligible', 'tiebreak_mode', 'tiebreak_secondary', 'mark_judge_count',
    ];

    protected $casts = [
        'criteria_json' => 'array',
        'fee_amount' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'group_item_flat_fee' => 'decimal:2',
        'group_item_per_participant_rate' => 'decimal:2',
        'is_enabled' => 'boolean',
        'is_mandatory' => 'boolean',
        // date:Y-m-d — plain-date serialization. Bare 'date' casts serialize to a UTC
        // ISO timestamp (2026-07-25 IST → "2026-07-24T18:30:00Z"), so date inputs
        // display the previous day and each save silently shifts the date back one.
        'reg_start' => 'date:Y-m-d',
        'reg_end' => 'date:Y-m-d',
        'competition_start' => 'date:Y-m-d',
        'competition_end' => 'date:Y-m-d',
        'results_published_at' => 'datetime',
        'results_hidden' => 'boolean',
        'quota_eligible' => 'boolean',
    ];

    protected $appends = [
        'squad_summary',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->category === null) {
                $item->category = 'general';
            }
            if ($item->participant_type === null) {
                $item->participant_type = 'individual';
            }
            if ($item->gender === null) {
                $item->gender = 'open';
            }
            if ($item->class_group === null) {
                $item->class_group = 'open';
            }
        });
    }

    public function getSquadSummaryAttribute(): ?string
    {
        return $this->squadSummary();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FestItemHead::class, 'head_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(FestCompetitionArea::class, 'area_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(FestEventPhase::class, 'phase_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'item_id');
    }

    public function squadRules(): ?FestTeamSquadRules
    {
        return FestTeamSquadRules::fromItem($this);
    }

    public function squadSummary(): ?string
    {
        return $this->squadRules()?->summary();
    }

    public function validateSquadCount(int $count): ?string
    {
        return $this->squadRules()?->validateCount($count);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function isStateCatalog(): bool
    {
        return $this->owner_level === 'state';
    }

    public function isEditableBySahodaya(): bool
    {
        return $this->owner_level !== 'state';
    }

    public function isEditableBySchool(): bool
    {
        return $this->owner_level === 'school';
    }

    /** Team/group/pair/trio items are billed once per entry via the head's team_registration_fee, not per member. */
    public function isTeamItem(): bool
    {
        return FestTeamSquadRules::isMultiPerson($this->participant_type);
    }
}
