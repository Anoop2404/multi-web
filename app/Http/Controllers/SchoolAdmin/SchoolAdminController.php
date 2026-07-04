<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\StudentEditChangeRequest;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Students\SchoolClassProvisioner;
use App\Support\SchoolContactRequirements;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class SchoolAdminController extends Controller
{
    protected Tenant $school;
    protected bool $isStaff = false;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->school = Tenant::findOrFail($tenantId);
        abort_unless($this->school->type === 'school', 403, 'School admin access requires a school tenant.');
        $this->isStaff = (bool) $request->attributes->get('isSchoolStaff', false);

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
        $staffPermissions = null;
        if ($this->isStaff && ($user = request()->user())) {
            $staffPermissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return inertia($component, array_merge([
            'school' => array_merge(
                $this->school->only('id', 'name', 'type', 'school_prefix', 'prefixes_locked', 'membership_status'),
                ['logo_url' => TenantBranding::logoUrl($this->school)],
            ),
            'publicUrl'  => TenantDomainSync::publicUrl($this->school),
            'isStaff'    => $this->isStaff,
            'staffPermissions' => $staffPermissions,
            'leadershipContacts' => SchoolContactRequirements::status($this->school),
            'pendingChangeRequests' => StudentEditChangeRequest::where('school_id', $this->school->id)
                ->where('status', 'pending')
                ->count(),
        ], $props));
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
}
