<?php

namespace App\Models;

use App\Support\FestTeamSquadRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEventItem extends Model
{
    protected $fillable = [
        'event_id', 'title', 'item_code', 'category', 'stage_type', 'venue_type',
        'competition_format', 'sport_discipline', 'duration_minutes', 'criteria_json',
        'participant_type', 'gender', 'class_group', 'age_group', 'kids_band',
        'max_per_school', 'min_group_size', 'max_group_size', 'qualify_count',
        'owner_level', 'state_program_item_id', 'inherited_from_item_id', 'display_order',
        'fee_amount', 'is_enabled',
    ];

    protected $casts = [
        'criteria_json' => 'array',
        'fee_amount' => 'decimal:2',
        'is_enabled' => 'boolean',
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
}
