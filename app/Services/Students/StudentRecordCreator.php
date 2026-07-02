<?php

namespace App\Services\Students;

use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\DataChangeLogger;
use App\Support\StudentRecordHelper;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;

class StudentRecordCreator
{
    /** @param  array<string, mixed>  $fields */
    public function create(Tenant $school, array $fields, ?UploadedFile $photo = null): Student
    {
        $payload = [
            'tenant_id'        => $school->id,
            'school_class_id'  => $fields['school_class_id'],
            'name'             => $fields['name'],
            'gender'           => $fields['gender'],
            'dob'              => $fields['dob'] ?? null,
            'status'           => 'active',
            'academic_year_id' => StudentRecordHelper::activeAcademicYearIdForSchool($school),
        ];

        $regNo = app(StudentRegistrationNumberGenerator::class)->generate($school);
        $payload['admission_number'] = $regNo;
        $payload['reg_no'] = $regNo;

        if (! empty($fields['parent_email'])) {
            $payload['parent_email'] = strtolower((string) $fields['parent_email']);
        } elseif (! empty($fields['email'])) {
            $payload['email'] = strtolower((string) $fields['email']);
        }

        if ($photo) {
            $payload['photo'] = TenantStorage::storeStudentPhoto($photo, $school->id);
        } elseif (! empty($fields['photo']) && is_string($fields['photo'])) {
            $payload['photo'] = $fields['photo'];
        }

        $student = Student::create($payload);

        app(DataChangeLogger::class)->created(
            $student,
            "Student registered: {$student->name}",
            $school->id,
            'students',
            ['school_class_id' => $student->school_class_id],
        );

        return $student;
    }
}
