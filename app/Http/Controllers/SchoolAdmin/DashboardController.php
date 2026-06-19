<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AdmissionEnquiry;
use App\Models\NewsArticle;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TcRequest;
use App\Support\AcademicYear;

class DashboardController extends SchoolAdminController
{
    public function index()
    {
        $tid = $this->school->id;

        return $this->inertia('School/Dashboard', [
            'stats' => [
                ['label' => 'Active Students',      'value' => Student::where('tenant_id', $tid)->where('status', 'active')->count()],
                ['label' => 'News Articles',       'value' => NewsArticle::where('tenant_id', $tid)->count()],
                ['label' => 'New Enquiries',       'value' => AdmissionEnquiry::where('tenant_id', $tid)->where('status', 'new')->count()],
                ['label' => 'Pending TC Requests', 'value' => TcRequest::where('tenant_id', $tid)->where('status', 'pending')->count()],
            ],
            'setup' => $this->setupStatus(),
            'membershipComplete' => $this->membershipComplete(),
        ]);
    }

    private function setupStatus(): array
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

        $schoolCode = $this->school->school_prefix;
        $sahodayaPrefix = $profile?->prefix;
        $regNoExample = ($sahodayaPrefix && $schoolCode)
            ? strtoupper($sahodayaPrefix).'/'.strtoupper($schoolCode).'/'.AcademicYear::yearSuffix($academicYear).'/0001'
            : null;

        $classCount = $this->schoolClasses()->count();
        $studentCount = Student::where('tenant_id', $this->school->id)->where('status', 'active')->count();

        return [
            'academicYear'    => $academicYear,
            'hasSchoolCode'   => filled($schoolCode),
            'schoolCode'      => $schoolCode,
            'codeLocked'      => (bool) $this->school->prefixes_locked,
            'suggestedCode'   => strtoupper(substr(preg_replace('/[^a-z]/i', '', $this->school->name), 0, 3)) ?: 'SCH',
            'regNoExample'    => $regNoExample,
            'hasClasses'      => $classCount > 0,
            'classCount'      => $classCount,
            'studentCount'    => $studentCount,
            'hasRegistration' => (bool) $registration,
            'registrationStatus' => $registration?->registration_status,
        ];
    }

    private function membershipComplete(): ?array
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->first();

        if ($registration?->registration_status !== 'completed') {
            return null;
        }

        return [
            'academicYear' => $academicYear,
            'regNo'        => $registration->reg_no,
        ];
    }
}
