<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class RegistrationLandingController extends Controller
{
    use RendersPublicPages;

    public function __invoke(Request $request)
    {
        $tenant = $this->resolveTenant();

        $branding = $tenant->type === 'sahodaya'
            ? SahodayaHomepageContent::get($tenant)
            : [];

        return view('public.registration-landing', [
            'tenant'     => $tenant,
            'isSahodaya' => $tenant->type === 'sahodaya',
            'logoUrl'    => TenantBranding::logoUrl($tenant),
            'eyebrow'    => $branding['eyebrow'] ?? 'CBSE Sahodaya',
            'tagline'    => $branding['tagline'] ?? null,
            'motto'      => $branding['motto'] ?? null,
            'phone'      => $branding['phone'] ?? null,
            'email'      => $branding['email'] ?? null,
        ]);
    }
}
