<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Support\NavConfigDefaults;
use App\Support\PortalNavLinks;
use App\Support\SahodayaHomepageContent;
use App\Support\SchoolPublicPageContent;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class RegistrationLandingController extends Controller
{
    use RendersPublicPages;

    public function __invoke(Request $request)
    {
        $tenant = $this->resolveTenant();
        $isSahodaya = $tenant->type === 'sahodaya';

        if ($isSahodaya) {
            $branding = SahodayaHomepageContent::get($tenant);
            $navConfig = NavConfigDefaults::resolve(
                $tenant,
                $tenant->getSetting('nav_config', [])
            );
            $portalCta = array_merge(
                PortalNavLinks::portalCtaDefaults(),
                $navConfig['portal_cta'] ?? []
            );
            $landingContent = [];
        } else {
            $siteContent = SchoolPublicPageContent::resolve($tenant);
            $contact = $tenant->getSetting('contact', []) ?? [];
            $seo = $tenant->getSetting('seo', []) ?? [];
            $landingContent = $siteContent['pages']['portal_landing'] ?? [];
            $branding = [
                'eyebrow' => $landingContent['eyebrow'] ?? 'School Website',
                'tagline' => $siteContent['branding']['subtitle'] ?? null,
                'motto' => $seo['tagline'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'email' => $contact['email'] ?? null,
            ];
            $portalCta = [
                'login_label' => $landingContent['action_label'] ?? 'School Administration Login',
                'login_url' => '/school-login',
            ];
        }

        return view('public.registration-landing', [
            'tenant' => $tenant,
            'isSahodaya' => $isSahodaya,
            'logoUrl' => TenantBranding::logoUrl($tenant),
            'eyebrow' => $branding['eyebrow'] ?? 'CBSE Sahodaya',
            'tagline' => $branding['tagline'] ?? null,
            'motto' => $branding['motto'] ?? null,
            'phone' => $branding['phone'] ?? null,
            'email' => $branding['email'] ?? null,
            'portalCta' => $portalCta,
            'landingContent' => $landingContent,
        ]);
    }
}
