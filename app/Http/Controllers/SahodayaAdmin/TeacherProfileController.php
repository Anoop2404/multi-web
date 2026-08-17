<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Concerns\ManagesTeacherPortalCredentials;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class TeacherProfileController extends SahodayaAdminController
{
    use ManagesTeacherPortalCredentials;

    public function show(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 404);
        abort_if($this->membershipRegionScopedSchoolIds([$teacher->tenant_id]) === [], 404);

        $teacher->load([
            'verifiedBy:id,name,email',
            'user:id,username,plain_password',
        ]);

        $school = Tenant::findOrFail($teacher->tenant_id);

        return $this->inertia('Sahodaya/Teachers/Show', [
            'teacher'        => $this->profilePayload($teacher, $school),
            'school'         => $school->only('id', 'name', 'school_prefix'),
            'portalLoginUrl' => url('/portal/login'),
        ]);
    }

    public function provisionPortal(Request $request, string $tenantId, Teacher $teacher)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 403);
        abort_if($this->membershipRegionScopedSchoolIds([$teacher->tenant_id]) === [], 403);

        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        return $this->provisionTeacherPortalLogin($teacher, $data['email']);
    }

    public function resetPortalPassword(string $tenantId, Teacher $teacher)
    {
        $this->assertStaffCan('membership.manage');
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 403);
        abort_if($this->membershipRegionScopedSchoolIds([$teacher->tenant_id]) === [], 403);

        return $this->resetTeacherPortalPassword($teacher, request()->user()?->id);
    }

    /** On-demand reveal of the stored plaintext portal password — not sent in the initial page payload. */
    public function revealPortalPassword(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 404);
        abort_if($this->membershipRegionScopedSchoolIds([$teacher->tenant_id]) === [], 404);
        $this->assertStaffCan('membership.manage');

        $teacher->loadMissing('user:id,username,plain_password');

        app(\App\Services\Audit\PlatformAuditLogger::class)->log(
            'credential.viewed',
            "Teacher portal password viewed: {$teacher->name}",
            $teacher,
            ['teacher_id' => $teacher->id],
        );

        return response()->json([
            'username' => $teacher->login_code ?? $teacher->user?->username,
            'password' => $teacher->user?->plain_password,
        ]);
    }

    public function showPhoto(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant?->parent_id !== $this->sahodaya->id, 404);
        abort_if($this->membershipRegionScopedSchoolIds([$teacher->tenant_id]) === [], 404);
        abort_unless($teacher->photo, 404);

        $school = Tenant::findOrFail($teacher->tenant_id);

        try {
            return TenantStorage::downloadResponse($school, $teacher->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
    }

    /** @return array<string, mixed> */
    private function profilePayload(Teacher $teacher, Tenant $school): array
    {
        return [
            'id'                => $teacher->id,
            'name'              => $teacher->name,
            'reg_no'            => $teacher->reg_no,
            'employee_code'     => $teacher->employee_code,
            'gender'            => $teacher->gender,
            'dob'               => $teacher->dob?->format('Y-m-d'),
            'dob_display'       => $teacher->dob?->format('j M Y'),
            'email'             => $teacher->email,
            'mobile'            => $teacher->mobile,
            'address'           => $teacher->address,
            'designation'       => $teacher->designation,
            'subjects'          => $teacher->subjects->pluck('label')->values()->all(),
            'qualification'     => $teacher->qualification,
            'experience_years'  => $teacher->experience_years,
            'date_of_joining'   => $teacher->date_of_joining?->format('Y-m-d'),
            'employment_status' => $teacher->employment_status,
            'status'            => $teacher->status,
            'is_verified'       => $teacher->isVerified(),
            'verified_at'       => $teacher->verified_at?->toIso8601String(),
            'verified_by'       => $teacher->verifiedBy?->name ?? $teacher->verifiedBy?->email,
            'rejection_reason'  => $teacher->rejection_reason,
            'has_portal_login'  => $teacher->user_id !== null,
            'portal_username'   => $teacher->login_code ?? $teacher->user?->username,
            'photo_url'         => $teacher->photoUrl(),
            'school_id'         => $school->id,
            'school_name'       => $school->name,
        ];
    }
}
