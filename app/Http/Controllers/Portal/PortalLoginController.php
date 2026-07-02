<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class PortalLoginController extends Controller
{
    /** School-branded portal login via Sahodaya site, e.g. /portal/s/DEMO/login */
    public function school(string $schoolCode): Response|RedirectResponse
    {
        if (TenantDomainSync::isCentralHost(request()->getHost())) {
            return redirect()->route('login');
        }

        $hostTenant = TenantBranding::resolveTenant();
        if (! $hostTenant || $hostTenant->type !== 'sahodaya') {
            return redirect()->route('portal.login');
        }

        $school = Tenant::query()
            ->where('parent_id', $hostTenant->id)
            ->where('type', 'school')
            ->where('school_prefix', strtoupper($schoolCode))
            ->first();

        if (! $school) {
            return redirect()->route('portal.login')
                ->withErrors(['email' => 'School not found. Check your login link or use the general portal login.']);
        }

        return inertia('Auth/PortalLogin', [
            'logoUrl'        => TenantBranding::logoUrl($school) ?: TenantBranding::logoUrl($hostTenant),
            'tenantName'     => $school->name,
            'motto'          => SahodayaHomepageContent::get($hostTenant)['motto'] ?? null,
            'sessionExpired' => request()->query('session') === 'expired',
            'schoolContext'  => $school->only('id', 'name', 'school_prefix'),
        ]);
    }
}
