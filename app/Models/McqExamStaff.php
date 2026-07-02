<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McqExamStaff extends Model
{
    protected $table = 'mcq_exam_staff';

    protected $fillable = ['exam_id', 'user_id', 'role'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(McqExam::class, 'exam_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
