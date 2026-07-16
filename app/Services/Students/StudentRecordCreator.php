<?php

namespace App\Services\Students;

use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\DataChangeLogger;
use App\Support\StudentRecordHelper;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StudentRecordCreator
{
    /** @param  array<string, mixed>  $fields */
    public function create(Tenant $school, array $fields, ?UploadedFile $photo = null): Student
    {
        $academicYearId = StudentRecordHelper::activeAcademicYearIdForSchool($school);

        $payload = [
            'tenant_id'        => $school->id,
            'school_class_id'  => $fields['school_class_id'],
            'name'             => $fields['name'],
            'gender'           => $fields['gender'],
            'dob'              => $fields['dob'] ?? null,
            'status'           => 'active',
            'academic_year_id' => $academicYearId,
        ];

        $payload['reg_no'] = app(StudentRegistrationNumberGenerator::class)->generate($school);

        if (! empty($fields['admission_number'])) {
            $payload['admission_number'] = trim((string) $fields['admission_number']);
            $this->assertAdmissionNumberAvailable($school, $academicYearId, $payload['admission_number']);
        }

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

        if ($school->parent_id) {
            app(\App\Services\Notifications\SahodayaAdminNotifier::class)->notifyAdmins(
                $school->parent_id,
                'student.verification.pending',
                ['student_name' => $student->name],
                "/sahodaya-admin/{$school->parent_id}/students/verification",
            );
        }

        return $student;
    }

    /**
     * School admission numbers only need to be unique within one school for
     * one academic year (matches the students_tenant_year_admission_unique
     * partial DB index) — the same number can be reused in a later year.
     */
    public function assertAdmissionNumberAvailable(Tenant $school, ?int $academicYearId, string $admissionNumber, ?int $ignoreStudentId = null): void
    {
        $exists = Student::query()
            ->where('tenant_id', $school->id)
            ->where('academic_year_id', $academicYearId)
            ->whereRaw('lower(admission_number) = ?', [strtolower($admissionNumber)])
            ->when($ignoreStudentId, fn ($q) => $q->where('id', '!=', $ignoreStudentId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'admission_number' => "Admission number \"{$admissionNumber}\" already exists in this school for this academic year.",
            ]);
        }
    }
}
