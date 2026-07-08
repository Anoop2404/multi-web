<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Concerns\ManagesStudentPortalCredentials;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\StudentSportsProfileService;
use App\Support\TenantStorage;

class StudentProfileController extends SahodayaAdminController
{
    use ManagesStudentPortalCredentials;

    public function show(string $tenantId, Student $student)
    {
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 404);

        $student->load([
            'schoolClass.classCategory',
            'schoolHouse',
            'user:id,username,plain_password',
            'verifiedBy:id,name,email',
        ]);

        $school = Tenant::findOrFail($student->tenant_id);

        $sportsProfile = app(StudentSportsProfileService::class)->forStudent($student, $school->id);

        return $this->inertia('Sahodaya/Students/Show', [
            'student'        => $this->profilePayload($student, $school),
            'school'         => $school->only('id', 'name', 'school_prefix'),
            'sportsProfile'  => $sportsProfile,
            'portalLoginUrl' => url('/portal/login'),
        ]);
    }

    public function provisionPortal(string $tenantId, Student $student)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 403);

        return $this->provisionStudentPortalLogin($student);
    }

    public function resetPortalPassword(string $tenantId, Student $student)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 403);

        return $this->resetStudentPortalPassword($student, request()->user()?->id);
    }

    public function showPhoto(string $tenantId, Student $student)
    {
        abort_if($student->tenant?->parent_id !== $this->sahodaya->id, 404);
        abort_unless($student->photo, 404);

        $school = Tenant::findOrFail($student->tenant_id);

        try {
            return TenantStorage::downloadResponse($school, $student->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
    }
    /** @return array<string, mixed> */
    private function profilePayload(Student $student, Tenant $school): array
    {
        $photoUrl = $student->photo
            ? route('sahodaya.students.photo', [
                'tenantId' => $this->sahodaya->id,
                'student'  => $student->id,
            ], false)
            : null;

        return [
            'id'               => $student->id,
            'name'             => $student->name,
            'reg_no'           => $student->reg_no,
            'roll_number'      => $student->roll_number,
            'gender'           => $student->gender,
            'dob'              => $student->dob?->format('Y-m-d'),
            'dob_display'      => $student->dob?->format('j M Y'),
            'age_years'        => $student->dob ? (int) $student->dob->diffInYears(now()) : null,
            'blood_group'      => $student->blood_group,
            'email'            => $student->email,
            'parent_name'      => $student->parent_name,
            'parent_phone'     => $student->parent_phone,
            'parent_email'     => $student->parent_email,
            'address'          => $student->address,
            'admission_date'   => $student->admission_date?->format('Y-m-d'),
            'status'           => $student->status,
            'notes'            => $student->notes,
            'class_name'       => $student->schoolClass?->name,
            'category_label'   => $student->schoolClass?->classCategory?->label,
            'house_name'       => $student->schoolHouse?->name,
            'is_verified'      => $student->isVerified(),
            'verified_at'      => $student->verified_at?->toIso8601String(),
            'verified_by'      => $student->verifiedBy?->name ?? $student->verifiedBy?->email,
            'has_portal_login' => $student->user_id !== null,
            'portal_username'  => $student->user?->username ?? $student->reg_no,
            'portal_password'  => $student->user?->plain_password,
            'photo_url'        => $photoUrl,
            'school_id'        => $school->id,
            'school_name'      => $school->name,
        ];
    }
}
