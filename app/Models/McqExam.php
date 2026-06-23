<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McqExam extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'exam_type', 'conductor_level',
        'scheduled_at', 'duration_minutes', 'total_questions', 'pass_mark', 'status', 'settings_json',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'settings_json' => 'array',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(McqRegistration::class, 'exam_id');
    }
}
