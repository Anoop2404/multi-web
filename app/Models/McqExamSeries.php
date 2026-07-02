<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McqExamSeries extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'academic_year_id', 'description', 'status',
    ];

    public function exams(): HasMany
    {
        return $this->hasMany(McqExam::class, 'series_id')->orderBy('exam_level');
    }
}
