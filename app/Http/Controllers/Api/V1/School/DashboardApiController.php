<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AcademicYear;

class DashboardApiController extends SchoolApiController
{
    public function index()
    {
        $tid = $this->school->id;
        $academicYear = AcademicYear::forSchool($this->school);
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

        $schoolCode = $this->school->school_prefix;
        $regNoExample = \App\Services\Students\StudentRegistrationNumberGenerator::PREFIX.'/'.AcademicYear::yearSuffix($academicYear).'/0001';

        $classCount = SchoolClass::where('tenant_id', $tid)->where('is_active', true)->count();
        $studentCount = Student::where('tenant_id', $tid)->where('status', 'active')->count();

        return $this->ok([
            'school' => [
                'id'            => $this->school->id,
                'name'          => $this->school->name,
                'school_prefix' => $schoolCode,
            ],
            'stats' => [
                'active_students' => $studentCount,
                'classes'         => $classCount,
            ],
            'setup' => [
                'academic_year'       => $academicYear,
                'has_school_code'     => filled($schoolCode),
                'school_code'         => $schoolCode,
                'code_locked'         => (bool) $this->school->prefixes_locked,
                'suggested_code'      => strtoupper(substr(preg_replace('/[^a-z]/i', '', $this->school->name), 0, 3)) ?: 'SCH',
                'reg_no_example'      => $regNoExample,
                'has_classes'         => $classCount > 0,
                'class_count'         => $classCount,
                'student_count'       => $studentCount,
                'has_registration'    => (bool) $registration,
                'registration_status' => $registration?->registration_status,
            ],
            'membership_complete' => $registration?->registration_status === 'completed'
                ? ['academic_year' => $academicYear, 'reg_no' => $registration->reg_no]
                : null,
        ]);
    }
}
