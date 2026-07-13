<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEvent extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'event_type', 'conductor_level',
        'conduct_levels', 'level_round', 'state_program_id', 'conducting_school_id',
        'is_cascaded', 'parent_event_id',         'cluster_key', 'cluster_label', 'cloned_from_event_id',
        'conduct_mode', 'partition_role', 'partition_key', 'aggregation_config', 'scoring_preset',
        'registration_open', 'registration_close', 'event_start', 'event_end', 'sports_age_cutoff_date', 'venue',
        'fee_type', 'fee_amount', 'fee_settings', 'numbering_settings', 'status', 'nav_hidden', 'results_published', 'description',
        'scoring_locked', 'appeals_open', 'chest_reveal_mode', 'require_judge_scores_before_publish',
        'appeal_fee_amount', 'certificate_collection_open', 'registration_locked', 'schedule_published',
        'record_tracking_enabled', 'default_record_prize_label', 'require_all_marks_before_publish',
        'require_event_registration', 'event_reg_start', 'event_reg_end', 'allow_student_self_register',
        'verification_day', 'manual_pdf_path',
        'sport_discipline', 'source_head_id',
    ];

    protected $casts = [
        'is_cascaded'                         => 'boolean',
        'nav_hidden'                          => 'boolean',
        'results_published'                   => 'boolean',
        'scoring_locked'                      => 'boolean',
        'appeals_open'                        => 'boolean',
        'require_judge_scores_before_publish' => 'boolean',
        'certificate_collection_open'         => 'boolean',
        'registration_locked'                 => 'boolean',
        'schedule_published'                  => 'boolean',
        'require_all_marks_before_publish'    => 'boolean',
        'require_event_registration'          => 'boolean',
        'allow_student_self_register'         => 'boolean',
        'record_tracking_enabled'             => 'boolean',
        'conduct_levels'                      => 'array',
        'aggregation_config'                  => 'array',
        'registration_open'                   => 'date',
        'registration_close'                  => 'date',
        'event_reg_start'                     => 'date',
        'event_reg_end'                       => 'date',
        'event_start'                         => 'date',
        'event_end'                           => 'date',
        'verification_day'                    => 'date',
        'sports_age_cutoff_date'              => 'date',
        'fee_amount'                          => 'decimal:2',
        'fee_settings'                        => 'array',
        'numbering_settings'                  => 'array',
        'appeal_fee_amount'                   => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $event) {
            if ($event->fee_type === null) {
                $event->fee_type = 'none';
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestEventItem::class, 'event_id')->orderBy('display_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'event_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(FestResult::class, 'event_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(FestEvent::class, 'parent_event_id');
    }

    public function houses(): HasMany
    {
        return $this->hasMany(FestHouse::class, 'event_id')->orderBy('sort_order');
    }

    public function conductingSchool(): BelongsTo
    {
        return $this->belongsToCentralTenant('conducting_school_id');
    }

    public function scopeForTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('event_type', $type);
    }

    public function scopeVisibleInNav($q)
    {
        return $q->where('nav_hidden', false);
    }

    /**
     * The single top-level Sahodaya hub event for a program type & year.
     * Excludes school rounds, partition/region child events, and cluster spawns.
     */
    public function scopePrimaryHub($q)
    {
        return $q->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->where(function ($role) {
                $role->whereNull('partition_role')
                    ->orWhere('partition_role', 'sports_season');
            })
            ->where(function ($inner) {
                $inner->whereIn('level_round', ['sahodaya', 'state'])
                    ->orWhereNull('level_round');
            });
    }

    public function isSportsDisciplineEvent(): bool
    {
        return $this->event_type === 'sports'
            && ($this->partition_role === 'sports_discipline' || $this->parent_event_id !== null);
    }

    public function isSportsSeasonEvent(): bool
    {
        return $this->event_type === 'sports'
            && $this->parent_event_id === null
            && ($this->partition_role === null || $this->partition_role === 'sports_season');
    }

    /** Fest program types that are unique (one per Sahodaya per academic year). */
    public static function singletonEventTypes(): array
    {
        return ['kalolsavam', 'sports', 'kids_fest', 'teacher_fest', 'english_fest', 'science_fest'];
    }

    public static function isSingletonType(?string $eventType): bool
    {
        return $eventType !== null && in_array($eventType, self::singletonEventTypes(), true);
    }

    public function scopeVisibleToSchool($q, string $schoolId)
    {
        return $q->where('nav_hidden', false)->where(function ($inner) use ($schoolId) {
            $inner->where(function ($cluster) {
                $cluster->where('level_round', 'sahodaya')
                    ->orWhereNull('level_round');
            })->orWhere(function ($school) use ($schoolId) {
                $school->where('level_round', 'school')
                    ->where('conducting_school_id', $schoolId);
            });
        });
    }

    /**
     * Events schools may list (hub, nav switcher, registration, API).
     * Sports: only once registration opens (draft/published stay Sahodaya-only).
     * Other fest types: published preview remains allowed.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\FestEvent>  $q
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\FestEvent>
     */
    public function scopeListedForSchool($q, string $schoolId, ?string $eventType = null)
    {
        $q->visibleToSchool($schoolId);

        if ($eventType === 'sports') {
            return $q->whereIn('status', self::schoolListStatusesForType('sports'))
                ->where(function ($inner) {
                    // Prefer discipline events; hide season umbrella once partition_role is set.
                    $inner->where('partition_role', 'sports_discipline')
                        ->orWhereNull('partition_role')
                        ->orWhere('partition_role', '!=', 'sports_season');
                });
        }

        if ($eventType !== null) {
            return $q->whereIn('status', self::schoolListStatusesForType($eventType));
        }

        // Mixed queries: sports rows require registration_open+; others keep published+.
        return $q->where(function ($inner) {
            $inner->where(function ($sports) {
                $sports->where('event_type', 'sports')
                    ->whereIn('status', self::schoolListStatusesForType('sports'));
            })->orWhere(function ($other) {
                $other->where(function ($nonSports) {
                    $nonSports->whereNull('event_type')
                        ->orWhere('event_type', '!=', 'sports');
                })->whereIn('status', self::schoolListStatusesForType(null));
            });
        });
    }

    /** @return list<string> */
    public static function schoolListStatusesForType(?string $eventType): array
    {
        if ($eventType === 'sports') {
            return ['registration_open', 'ongoing', 'completed'];
        }

        return ['published', 'registration_open', 'ongoing', 'completed'];
    }

    public function conductsAt(string $level): bool
    {
        return in_array($level, $this->conduct_levels ?? ['sahodaya'], true);
    }

    public function isStateProgram(): bool
    {
        return $this->state_program_id !== null;
    }

    public function isEditableBySahodaya(): bool
    {
        return ! $this->isStateProgram() || $this->level_round !== 'state';
    }

    /** @return array<string, string> */
    public static function levelLabels(): array
    {
        return FestStateProgram::levelLabels();
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->status !== 'registration_open') {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->registration_open && $today->lt($this->registration_open)) {
            return false;
        }

        if ($this->registration_close && $today->gt($this->registration_close)) {
            return false;
        }

        return true;
    }
}
