<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolYearSubmission extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id', 'academic_year',
        'full_records_status', 'full_records_rejection_reason',
        'counts_status', 'counts_rejection_reason',
        'teacher_status', 'teacher_rejection_reason',
        'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function school()    { return $this->belongsToCentralTenant('school_id'); }
    public function registration() { return $this->hasOne(Registration::class); }
    public function students()  { return $this->hasMany(SubmissionStudent::class); }
    public function counts()    { return $this->hasMany(SchoolYearStudentCount::class); }
    public function teachers()  { return $this->hasMany(SubmissionTeacher::class); }

    /**
     * Whether the tracks that actually gate membership payment are approved. Teacher data still
     * goes through its own submit/verify/approve workflow (see teacher_status) so Sahodaya can
     * review it, but it is informational only and must never block a school from paying its
     * membership fee — only student data (full records or counts) gates payment.
     */
    public function allApplicableTracksApproved(SahodayaProfile $profile): bool
    {
        if ($profile->student_data_mode === 'full_records' && $this->full_records_status !== 'approved') {
            return false;
        }
        if ($profile->student_data_mode === 'counts_only' && $this->counts_status !== 'approved') {
            return false;
        }

        return true;
    }
}
