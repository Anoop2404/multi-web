<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Students\SchoolClassProvisioner;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class SchoolAdminController extends Controller
{
    protected Tenant $school;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->school = Tenant::findOrFail($tenantId);

        if ($this->school->parent_id) {
            app(SchoolClassProvisioner::class)->ensureForSchool($this->school);
        }
    }

    protected function inertia(string $component, array $props = [])
    {
        return inertia($component, array_merge([
            'school' => array_merge(
                $this->school->only('id', 'name', 'type', 'school_prefix', 'prefixes_locked'),
                ['logo_url' => TenantBranding::logoUrl($this->school)],
            ),
            'publicUrl' => TenantDomainSync::publicUrl($this->school),
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
