<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionStudent extends Model
{
    protected $fillable = [
        'school_year_submission_id', 'school_class_id', 'name', 'class', 'section',
        'gender', 'dob', 'image_path', 'guardian_name', 'guardian_phone',
    ];

    protected $casts = ['dob' => 'date'];

    public function submission()  { return $this->belongsTo(SchoolYearSubmission::class, 'school_year_submission_id'); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class); }

    public function imageUrl(?string $schoolId = null): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        $schoolId = $schoolId ?? $this->submission?->school_id;
        if (! $schoolId) {
            return null;
        }

        return route('school.registration.students.image', [
            'tenantId' => $schoolId,
            'student'  => $this->id,
        ]);
    }
}
