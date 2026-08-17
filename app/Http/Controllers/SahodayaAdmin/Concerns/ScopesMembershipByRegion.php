<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\SchoolRegionAssignment;
use App\Models\StaffRegionAssignment;
use App\Support\AcademicYear;

/**
 * Shared by both SahodayaAdminController (web) and SahodayaApiController (JSON API) — both
 * expose a `protected Tenant $sahodaya` and both have controllers that pull in
 * BuildsMembershipExports, which calls membershipRegionScopeOrNull(). Keeping this here
 * instead of on just one base class is what BuildsMembershipExports actually needs.
 */
trait ScopesMembershipByRegion
{
    /**
     * Narrow a school-id list down to the current user's region(s) for Membership/Student
     * data — a separate, role-independent mechanism from Fest's region_admin/FestEventStaff
     * duty scoping. Driven purely by StaffRegionAssignment row presence: no rows means
     * unrestricted (a plain sahodaya_admin, or staff with no region restriction, never has
     * assignment rows), so no role check is needed here at all.
     *
     * @param  list<string>  $schoolIds
     * @return list<string>
     */
    protected function membershipRegionScopedSchoolIds(array $schoolIds, ?string $year = null): array
    {
        $scope = $this->membershipRegionScopeOrNull($year);

        return $scope === null ? $schoolIds : array_values(array_intersect($schoolIds, $scope));
    }

    /**
     * Query-builder-friendly form of the same scope: null means unrestricted (don't filter),
     * an array means "only these school ids" — usable directly in a ->whereIn() without first
     * materializing every school id in the tenant just to intersect them.
     *
     * @return list<string>|null
     */
    protected function membershipRegionScopeOrNull(?string $year = null): ?array
    {
        $userId = request()->user()?->id;
        if (! $userId) {
            return null;
        }

        $regionIds = StaffRegionAssignment::forTenant($this->sahodaya->id)
            ->forUser($userId)
            ->pluck('region_id');

        if ($regionIds->isEmpty()) {
            return null;
        }

        $year ??= AcademicYear::forSahodaya($this->sahodaya->id);

        return SchoolRegionAssignment::forTenant($this->sahodaya->id)
            ->forYear($year)
            ->whereIn('region_id', $regionIds)
            ->pluck('school_id')
            ->all();
    }
}
