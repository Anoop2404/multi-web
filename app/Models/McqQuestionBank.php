<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McqQuestionBank extends Model
{
    use BelongsToCentralTenant;

    /** `active` is shown as "Ready": only ready banks can be added to an exam paper. */
    public const STATUSES = ['draft', 'active', 'archived'];

    public const STATUS_LABELS = [
        'draft'    => 'Draft',
        'active'   => 'Ready',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'sahodaya_id', 'school_id', 'teacher_id', 'subject', 'class_group',
        'class_category_id', 'master_class_id', 'source_bank_id', 'contributed_by_school_id',
        'title', 'description', 'status', 'created_by_user_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function classCategory(): BelongsTo
    {
        return $this->belongsTo(ClassCategory::class, 'class_category_id');
    }

    public function masterClass(): BelongsTo
    {
        return $this->belongsTo(MasterClass::class, 'master_class_id');
    }

    public function sourceBank(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_bank_id');
    }

    public function contributedBySchool(): BelongsTo
    {
        return $this->belongsToCentralTenant('contributed_by_school_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(McqQuestion::class, 'bank_id')->orderBy('display_order');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(McqExam::class, 'mcq_exam_question_banks', 'bank_id', 'exam_id');
    }

    /**
     * True once any exam using this bank has a student who started writing it. From then on
     * the questions are frozen so every candidate is graded against the same paper.
     */
    public function isLockedByExam(): bool
    {
        $examIds = $this->exams()->pluck('mcq_exams.id');

        return $examIds->isNotEmpty()
            && McqRegistration::whereIn('exam_id', $examIds)->whereNotNull('started_at')->exists();
    }
}
