<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SahodayaProfile;
use App\Models\StudentEditChangeRequest;
use App\Models\Tenant;
use App\Support\SahodayaNavVisibility;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Students\SchoolClassProvisioner;
use App\Support\SchoolContactRequirements;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use App\Http\Controllers\SchoolAdmin\Concerns\BuildsSchoolFestEventContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class SchoolAdminController extends Controller
{
    use BuildsSchoolFestEventContext;

    protected Tenant $school;
    protected bool $isStaff = false;
    protected bool $isEventCoordinator = false;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->school = Tenant::findOrFail($tenantId);
        abort_unless($this->school->type === 'school', 403, 'School admin access requires a school tenant.');
        $this->isStaff = (bool) $request->attributes->get('isSchoolStaff', false);
        $this->isEventCoordinator = (bool) $request->attributes->get('isEventCoordinator', false);

        if ($this->isStaff && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $permission = \App\Support\TenantUserCatalog::writePermissionForPath($request->path());
            if ($permission === null || ! $request->user()?->can($permission)) {
                abort(403, 'View-only access. Contact your school administrator.');
            }
        }

        if ($this->school->parent_id) {
            app(SchoolClassProvisioner::class)->ensureForSchool($this->school);
        }
    }

    protected function inertia(string $component, array $props = [])
    {
        $props = $this->withSchoolFestNavContext($props);

        $staffPermissions = null;
        $eventScopes = null;
        $user = request()->user();

        if ($user && ($this->isStaff || $this->isEventCoordinator)) {
            $staffPermissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        if ($this->isEventCoordinator && $user) {
            $eventScopes = app(\App\Services\School\SchoolUserScopeService::class)
                ->scopesWithLabels($user->id, $this->school->id, $this->school->parent_id);
        }

        return inertia($component, array_merge([
            'school' => array_merge(
                $this->school->only('id', 'name', 'type', 'school_prefix', 'prefixes_locked', 'membership_status', 'fest_registration_closed'),
                ['logo_url' => TenantBranding::logoUrl($this->school)],
            ),
            'publicUrl'  => TenantDomainSync::publicUrl($this->school),
            'isStaff'    => $this->isStaff,
            'isEventCoordinator' => $this->isEventCoordinator,
            'eventScopes' => $eventScopes,
            'staffPermissions' => $staffPermissions,
            'leadershipContacts' => SchoolContactRequirements::status($this->school),
            'pendingChangeRequests' => StudentEditChangeRequest::where('school_id', $this->school->id)
                ->where('status', 'pending')
                ->count(),
            'navVisibility' => $this->school->parent_id
                ? SahodayaNavVisibility::forProfile(
                    SahodayaProfile::where('tenant_id', $this->school->parent_id)->first(),
                    $this->school->parent?->nav_overrides,
                )
                : SahodayaNavVisibility::defaults(),
            'membershipPaid' => app(\App\Services\Membership\SchoolMembershipGate::class)->isPaid($this->school),
        ], $props));
    }

    /** @param  array<string, mixed>  $props */
    protected function withSchoolFestNavContext(array $props): array
    {
        $event = $props['event'] ?? null;
        if (! is_array($event) || empty($event['id'])) {
            return $props;
        }

        $program = $props['program'] ?? ($props['programMeta']['slug'] ?? null);
        if (! is_string($program) || $program === '') {
            return $props;
        }

        if (isset($props['eventHeadNav'], $props['programPrefix'])) {
            return $props;
        }

        $festEvent = \App\Models\FestEvent::query()
            ->whereKey($event['id'])
            ->where('tenant_id', $this->school->parent_id)
            ->first();

        if (! $festEvent) {
            return $props;
        }

        return array_merge($this->schoolFestEventNavProps($festEvent, $program), $props);
    }

    protected function classCategories(): Collection
    {
        $sahodayaId = $this->school->parent_id;
        if (! $sahodayaId) {
            return collect();
        }

        return app(EffectiveMasterDataResolver::class)->classCategories($sahodayaId);
    }

    protected function schoolClasses(): Collection
    {
        return SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->with('classCategory')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /** School leadership can add/edit students even when the edit window is closed. */
    protected function canManageStudentsDirectly(): bool
    {
        if ($this->isStaff) {
            return false;
        }

        $user = request()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']);
    }

    protected function assertCanAddStudents(): void
    {
        if ($this->canManageStudentsDirectly()) {
            $this->assertNotEmergencyLocked();

            return;
        }

        app(\App\Services\Students\StudentEditLockService::class)->assertCanAdd($this->school);
    }

    protected function assertCanEditStudents(): void
    {
        if ($this->canManageStudentsDirectly()) {
            $this->assertNotEmergencyLocked();

            return;
        }

        app(\App\Services\Students\StudentEditLockService::class)->assertCanEdit($this->school);
    }

    protected function assertNotEmergencyLocked(): void
    {
        $state = app(\App\Services\Students\StudentEditLockService::class)->resolveWindowState($this->school);
        if ($state['source'] === 'emergency_lock') {
            abort(422, $state['message'] ?? 'Student records are frozen by Sahodaya.');
        }
    }
}
