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
        'title', 'exam_type', 'delivery_mode', 'conductor_level',
        'scheduled_at', 'venue', 'hall_instructions', 'next_hall_ticket_no',
        'duration_minutes', 'total_questions', 'pass_mark', 'status', 'settings_json',
        'fee_type', 'fee_amount',
        'eligibility_config',
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
        'scheduled_at'          => 'datetime',
        'settings_json'         => 'array',
        'eligibility_config'    => 'array',
        'promoted_student_ids'  => 'array',
        'fee_amount'            => 'decimal:2',
        'cutoff_score'          => 'decimal:2',
        'promotion_locked'      => 'boolean',
        'results_published'     => 'boolean',
        'results_published_at'  => 'datetime',
    ];

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

    public function isOnlineDelivery(): bool
    {
        return ($this->delivery_mode ?? 'offline') === 'online';
    }

    public function isOfflineDelivery(): bool
    {
        return ! $this->isOnlineDelivery();
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
