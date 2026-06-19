<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;

abstract class SahodayaAdminController extends Controller
{
    protected Tenant $sahodaya;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
    }

    protected function inertia(string $component, array $props = [])
    {
        return inertia($component, array_merge([
            'sahodaya'               => array_merge(
                $this->sahodaya->only('id', 'name', 'type'),
                ['logo_url' => TenantBranding::logoUrl($this->sahodaya)]
            ),
            'publicUrl'              => TenantDomainSync::publicUrl($this->sahodaya),
            'approvedSchoolsCount'   => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'approved')
                                            ->count(),
            'pendingSchoolsCount'    => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'pending')
                                            ->count(),
            'pendingSubmissionsCount'=> 0,
            'pendingPaymentsCount'   => \App\Models\MembershipPayment::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                                            ->where('status', 'submitted')->count(),
            'activeAcademicYear'     => \App\Support\AcademicYear::forSahodaya($this->sahodaya->id),
        ], $props));
    }
}
