<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Teacher extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'academic_year_id', 'reg_no', 'name', 'email',
        'designation', 'subject', 'teaching_type_id', 'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function teachingType(): BelongsTo
    {
        return $this->belongsTo(TeachingType::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
