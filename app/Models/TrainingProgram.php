<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'description', 'venue',
        'start_date', 'end_date', 'conductor_level',
        'registration_open', 'registration_close', 'max_participants',
        'allow_teacher_self_registration', 'qr_registration_token',
        'qr_registration_enabled', 'require_verified_teachers', 'allow_school_attendance',
        'attendance_qr_token', 'status',
        'fee_type', 'fee_amount', 'late_fee_amount', 'penalty_amount', 'eligibility_config',
    ];

    protected $casts = [
        'registration_open'  => 'date',
        'registration_close' => 'date',
        'start_date'         => 'date',
        'end_date'           => 'date',
        'allow_teacher_self_registration' => 'boolean',
        'qr_registration_enabled' => 'boolean',
        'require_verified_teachers' => 'boolean',
        'allow_school_attendance' => 'boolean',
        'fee_amount'         => 'decimal:2',
        'late_fee_amount'    => 'decimal:2',
        'penalty_amount'     => 'decimal:2',
        'eligibility_config' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $program) {
            if (! filled($program->qr_registration_token)) {
                $program->qr_registration_token = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(40));
            }
            if (! filled($program->attendance_qr_token)) {
                $program->attendance_qr_token = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(40));
            }
        });

        static::saving(function (self $program) {
            if ($program->fee_type === null) {
                $program->fee_type = 'none';
            }
        });
    }

    public function pendingSchools(): HasMany
    {
        return $this->hasMany(TrainingPendingSchool::class, 'program_id');
    }

    public function hasFee(): bool
    {
        return $this->fee_type !== 'none' && (float) $this->fee_amount > 0;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'program_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TrainingRegistration::class, 'program_id');
    }

    /** Number of training days (from sessions, or from start/end date span). */
    public function dayCount(): int
    {
        $sessions = $this->relationLoaded('sessions') ? $this->sessions : $this->sessions()->get();
        if ($sessions->isNotEmpty()) {
            return $sessions->count();
        }

        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date) + 1;
        }

        return $this->start_date ? 1 : 0;
    }
}
