<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class McqRegistration extends Model
{
    protected $fillable = [
        'exam_id', 'student_id', 'teacher_id', 'school_id', 'status', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(McqExam::class, 'exam_id');
    }

    public function mark(): HasOne
    {
        return $this->hasOne(McqMark::class, 'registration_id');
    }
}
