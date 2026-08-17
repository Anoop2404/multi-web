<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenancyDatabase;
use App\Support\TenantUserCatalog;

/**
 * One landing page linking every credential-touching screen for this Sahodaya — own staff,
 * schools, students, teachers — each of which has its own dedicated page/actions already.
 * This is navigation/summary only; no new credential-write capability lives here.
 */
class CredentialsHubController extends SahodayaAdminController
{
    public function index()
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $ownStaffCount = User::where('tenant_id', $this->sahodaya->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', TenantUserCatalog::sahodayaAssignableRoles()))
            ->count();

        $schoolIdsWithAUser = User::whereIn('tenant_id', $schoolIds)->distinct()->pluck('tenant_id');

        $schoolsWithoutLoginCount = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->whereNotIn('id', $schoolIdsWithAUser)
            ->count();

        $studentsWithoutLoginCount = Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNull('user_id')
            ->count();

        $teachersWithoutLoginCount = Teacher::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNull('user_id')
            ->count();

        $unverifiedTeachersCount = Teacher::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNull('verified_at')
            ->count();

        return $this->inertia('Sahodaya/Credentials/Hub', [
            'ownStaffCount'             => $ownStaffCount,
            'approvedSchoolsCount'      => Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->where('membership_status', 'approved')->count(),
            'schoolsWithoutLoginCount'  => $schoolsWithoutLoginCount,
            'studentsWithoutLoginCount' => $studentsWithoutLoginCount,
            'teachersWithoutLoginCount' => $teachersWithoutLoginCount,
            'unverifiedTeachersCount'   => $unverifiedTeachersCount,
        ]);
    }
}
