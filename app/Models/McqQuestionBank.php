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

    protected $fillable = [
        'sahodaya_id', 'school_id', 'teacher_id', 'subject', 'class_group',
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

    public function questions(): HasMany
    {
        return $this->hasMany(McqQuestion::class, 'bank_id')->orderBy('display_order');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(McqExam::class, 'mcq_exam_question_banks', 'bank_id', 'exam_id');
    }
}
