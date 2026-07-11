<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McqExam extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'series_id', 'exam_level', 'parent_exam_id',
        'eligibility_mode', 'cutoff_score', 'top_rank_count', 'promotion_locked', 'promoted_student_ids',
        'title', 'code', 'exam_type', 'delivery_mode', 'conductor_level',
        'scheduled_at', 'registration_opens_at', 'registration_closes_at', 'result_date',
        'venue', 'hall_instructions', 'question_paper_path', 'question_paper_label', 'next_hall_ticket_no',
        'duration_minutes', 'total_questions', 'pass_mark', 'status', 'settings_json',
        'fee_type', 'fee_amount', 'school_discount_amount',
        'payment_deadline', 'late_fee_amount', 'penalty_amount',
        'eligibility_config',
        'grade_master_id', 'hall_ticket_template_id', 'certificate_template_id',
        'results_published', 'results_published_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $exam) {
            if ($exam->duration_minutes === null) {
                $exam->duration_minutes = 60;
            }
            if ($exam->total_questions === null) {
                $exam->total_questions = 0;
            }
            if ($exam->fee_type === null) {
                $exam->fee_type = 'none';
            }
            if ($exam->delivery_mode === null) {
                $exam->delivery_mode = 'offline';
            }
            if ($exam->exam_type === null) {
                $exam->exam_type = 'assessment';
            }
        });
    }

    protected $casts = [
        'scheduled_at'             => 'datetime',
        'registration_opens_at'    => 'datetime',
        'registration_closes_at'   => 'datetime',
        'result_date'              => 'date',
        'settings_json'            => 'array',
        'eligibility_config'       => 'array',
        'promoted_student_ids'     => 'array',
        'fee_amount'               => 'decimal:2',
        'school_discount_amount'   => 'decimal:2',
        'payment_deadline'         => 'date',
        'late_fee_amount'          => 'decimal:2',
        'penalty_amount'           => 'decimal:2',
        'cutoff_score'             => 'decimal:2',
        'promotion_locked'         => 'boolean',
        'results_published'        => 'boolean',
        'results_published_at'     => 'datetime',
    ];

    /**
     * Whether registration is within an explicit date window (when set).
     * If neither opens_at nor closes_at is set, returns null so callers fall back to status.
     */
    public function registrationWindowActive(?\Carbon\CarbonInterface $at = null): ?bool
    {
        $at ??= now();
        $opens = $this->registration_opens_at;
        $closes = $this->registration_closes_at;

        if (! $opens && ! $closes) {
            return null;
        }

        if ($opens && $at->lt($opens)) {
            return false;
        }

        if ($closes && $at->gt($closes)) {
            return false;
        }

        return true;
    }

    public function isRegistrationOpen(?\Carbon\CarbonInterface $at = null): bool
    {
        if (! in_array($this->status, ['published', 'ongoing'], true)) {
            return false;
        }

        $window = $this->registrationWindowActive($at);

        return $window === null ? true : $window;
    }

    public function series()
    {
        return $this->belongsTo(McqExamSeries::class, 'series_id');
    }

    public function parentExam()
    {
        return $this->belongsTo(self::class, 'parent_exam_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(McqRegistration::class, 'exam_id');
    }

    public function hasFee(): bool
    {
        return ($this->fee_type ?? 'none') !== 'none' && (float) $this->fee_amount > 0;
    }

    public function schoolDiscountAmount(): float
    {
        if (! $this->hasFee()) {
            return 0.0;
        }

        $fee = (float) $this->fee_amount;

        return round(min($fee, max(0, (float) ($this->school_discount_amount ?? 0))), 2);
    }

    /** Amount the school remits to Sahodaya per registered student. */
    public function schoolPayablePerStudent(): float
    {
        if (! $this->hasFee()) {
            return 0.0;
        }

        return round((float) $this->fee_amount - $this->schoolDiscountAmount(), 2);
    }

    public function isOnlineDelivery(): bool
    {
        return ($this->delivery_mode ?? 'offline') === 'online';
    }

    public function isOfflineDelivery(): bool
    {
        return ! $this->isOnlineDelivery();
    }

    public function requiresHallTicket(): bool
    {
        return (bool) ($this->settings_json['requires_hall_ticket'] ?? false);
    }

    public function questionBanks(): BelongsToMany
    {
        return $this->belongsToMany(McqQuestionBank::class, 'mcq_exam_question_banks', 'exam_id', 'bank_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(McqExamStaff::class, 'exam_id');
    }

    public function schoolFees(): HasMany
    {
        return $this->hasMany(McqSchoolFee::class, 'exam_id');
    }
}
