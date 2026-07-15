<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolYearStudentCount extends Model
{
    protected $fillable = [
        'school_year_submission_id', 'class_category_id', 'school_class_id',
        'male_count', 'female_count', 'total_count',
    ];

    public function submission()    { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function classCategory() { return $this->belongsTo(ClassCategory::class); }
    public function schoolClass()   { return $this->belongsTo(SchoolClass::class); }

    public function hasMismatch(): bool
    {
        return $this->total_count !== ($this->male_count + $this->female_count);
    }
}
