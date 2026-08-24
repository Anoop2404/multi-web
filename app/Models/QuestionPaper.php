<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionPaper extends Model
{
    use BelongsToCentralTenant;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'teacher_id', 'school_class_id', 'class_name', 'subject_id', 'subject_name',
        'academic_year', 'title', 'exam_name', 'description', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'subject_id' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(QuestionPaperFile::class)->orderBy('display_order')->orderBy('id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
