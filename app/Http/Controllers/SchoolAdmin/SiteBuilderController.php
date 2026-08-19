<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SiteSection;
use App\Models\WebsiteSite;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\SchoolWebsiteTemplateCatalog;
use App\Support\SectionFieldRegistry;
use App\Support\TenantPublicSite;

class SiteBuilderController extends SchoolAdminController
{
    public function index(): \Inertia\Response
    {
        $site = WebsiteSite::ensurePrimary($this->school->id);

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
            'currentSite'          => [
                'id' => $site->id,
                'template_key' => $site->template_key,
                'template_version' => $site->template_version,
                'experience_version' => $site->experience_version,
                'design_json' => $site->design_json ?? [],
                'draft_template_json' => $site->draft_template_json,
            ],
            'experiences'          => SchoolWebsiteTemplateCatalog::summaries(),
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
}
