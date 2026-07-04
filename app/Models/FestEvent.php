<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'event_type', 'conductor_level',
        'conduct_levels', 'level_round', 'state_program_id', 'conducting_school_id',
        'is_cascaded', 'parent_event_id', 'cluster_key', 'cluster_label', 'cloned_from_event_id',
        'registration_open', 'registration_close', 'event_start', 'event_end', 'sports_age_cutoff_date', 'venue',
        'fee_type', 'fee_amount', 'fee_settings', 'numbering_settings', 'status', 'results_published', 'description',
        'scoring_locked', 'appeals_open', 'chest_reveal_mode', 'require_judge_scores_before_publish',
        'appeal_fee_amount', 'certificate_collection_open', 'registration_locked', 'schedule_published',
        'record_tracking_enabled', 'default_record_prize_label', 'require_all_marks_before_publish',
        'require_event_registration', 'event_reg_start', 'event_reg_end', 'allow_student_self_register',
        'verification_day', 'manual_pdf_path',
    ];

    protected $casts = [
        'is_cascaded'                         => 'boolean',
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
        return $this->belongsTo(Tenant::class, 'conducting_school_id');
    }

    public function scopeForTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('event_type', $type);
    }

    public function scopeVisibleToSchool($q, string $schoolId)
    {
        return $q->where(function ($inner) use ($schoolId) {
            $inner->where(function ($cluster) {
                $cluster->where('level_round', 'sahodaya')
                    ->orWhereNull('level_round');
            })->orWhere(function ($school) use ($schoolId) {
                $school->where('level_round', 'school')
                    ->where('conducting_school_id', $schoolId);
            });
        });
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
