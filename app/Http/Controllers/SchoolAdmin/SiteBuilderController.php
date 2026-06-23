<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SiteSection;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\SectionFieldRegistry;
use App\Support\TenantPublicSite;
use Illuminate\Http\JsonResponse;

class SiteBuilderController extends SchoolAdminController
{
    public function index(): \Inertia\Response
    {
        $sections = SiteSection::where('tenant_id', $this->school->id)
            ->orderBy('display_order')
            ->get();

        $navConfig = $this->school->getSetting('nav_config', []);
        $defaults = NavConfigDefaults::forSchool($this->school);
        $navConfig['portal_cta'] = array_merge(
            $defaults['portal_cta'] ?? SchoolPortalNavLinks::portalCtaDefaults(),
            $navConfig['portal_cta'] ?? []
        );

        return $this->inertia('School/SiteBuilder', [
            'sections'             => $sections,
            'sectionTypes'         => SchoolSiteBuilderCatalog::SECTION_TYPES,
            'fieldDefs'            => SectionFieldRegistry::all(),
            'navConfig'            => $navConfig,
            'footerConfig'         => $this->school->getSetting('footer_config', []),
            'portalDefaults'       => SchoolPortalNavLinks::portalCtaDefaults(),
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->school),
            'defaultNavConfig'     => $defaults,
            'navLayoutOptions'     => NavConfigDefaults::layoutOptions('school'),
            'navNeedsSetup'          => empty($navConfig['items']),
        ]);
    }

    public function sectionTypes(): JsonResponse
    {
        return response()->json([
            'types'     => SchoolSiteBuilderCatalog::SECTION_TYPES,
            'fieldDefs' => SectionFieldRegistry::all(),
        ]);
    }
}
