<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionTeacher extends Model
{
    protected $fillable = [
        'school_year_submission_id', 'name', 'subject', 'teaching_type_id',
    ];

    public function submission()    { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function teachingType()  { return $this->belongsTo(TeachingType::class); }
}
