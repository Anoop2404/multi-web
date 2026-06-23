<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SiteSection;
use App\Support\NavConfigDefaults;
use App\Support\PortalNavLinks;
use App\Support\SahodayaSiteBuilderCatalog;
use App\Support\SectionFieldRegistry;
use App\Support\SahodayaTenantBranding;
use App\Support\TenantPublicSite;
use Illuminate\Http\JsonResponse;

class SiteBuilderController extends SahodayaAdminController
{
    public function index(): \Inertia\Response
    {
        $sections = SiteSection::where('tenant_id', $this->sahodaya->id)
            ->orderBy('display_order')
            ->get();

        $navConfig = $this->sahodaya->getSetting('nav_config', []);
        $defaults = NavConfigDefaults::forSahodaya();
        $navConfig['portal_cta'] = array_merge(
            $defaults['portal_cta'] ?? PortalNavLinks::portalCtaDefaults(),
            $navConfig['portal_cta'] ?? []
        );

        return $this->inertia('Sahodaya/SiteBuilder', [
            'sections'      => $sections,
            'sectionTypes'  => SahodayaSiteBuilderCatalog::SECTION_TYPES,
            'fieldDefs'     => SectionFieldRegistry::all(),
            'navConfig'     => $navConfig,
            'footerConfig'  => $this->sahodaya->getSetting('footer_config', []),
            'portalDefaults'=> PortalNavLinks::portalCtaDefaults(),
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->sahodaya),
            'defaultNavConfig'     => $defaults,
            'navLayoutOptions'     => NavConfigDefaults::layoutOptions('sahodaya'),
            'navNeedsSetup'          => empty($navConfig['items']),
            'themeConfig'            => SahodayaTenantBranding::theme($this->sahodaya),
            'themePresets'           => SahodayaTenantBranding::themePresets(),
        ]);
    }

    public function sectionTypes(): JsonResponse
    {
        return response()->json([
            'types'    => SahodayaSiteBuilderCatalog::SECTION_TYPES,
            'fieldDefs'=> SectionFieldRegistry::all(),
        ]);
    }
}
