<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SiteSection;
use App\Models\WebsiteSite;
use App\Services\Licensing\FeatureGate;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolPublicPageContent;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\SchoolWebsiteTemplateCatalog;
use App\Support\SectionFieldRegistry;
use App\Support\SiteSectionMedia;
use App\Support\TenantPublicSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;

class SiteBuilderController extends SchoolAdminController
{
    public function index(): Response|RedirectResponse
    {
        if (! app(FeatureGate::class)->allows($this->school, 'module.website')) {
            return Redirect::back()->with(
                'error',
                'Your subscription does not include the public website module. Contact your Sahodaya administrator to enable it.',
            );
        }

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

        $mediaUrls = [];
        foreach ($sections as $section) {
            $mediaUrls = array_replace(
                $mediaUrls,
                SiteSectionMedia::urlMap(
                    $this->school,
                    $section->section_type,
                    $section->variant,
                    $section->config ?? []
                )
            );
        }

        $navMenuItems = $sections
            ->where('show_in_menu', true)
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'section_type' => $s->section_type,
                'variant' => $s->variant,
                'anchor' => '/#' . $s->section_type,
                'is_active' => $s->is_active,
                'display_order' => $s->display_order,
            ])
            ->all();

        return $this->inertia('School/SiteBuilder', [
            'sections' => $sections,
            'currentSite' => [
                'id' => $site->id,
                'template_key' => $site->template_key,
                'template_version' => $site->template_version,
                'experience_version' => $site->experience_version,
                'design_json' => $site->design_json ?? [],
                'draft_template_json' => $site->draft_template_json,
            ],
            'experiences' => SchoolWebsiteTemplateCatalog::summaries(),
            'sectionTypes' => SchoolSiteBuilderCatalog::SECTION_TYPES,
            'fieldDefs' => SectionFieldRegistry::all(),
            'navConfig' => $navConfig,
            'footerConfig' => $this->school->getSetting('footer_config', []),
            'siteContent' => SchoolPublicPageContent::resolve($this->school),
            'portalDefaults' => SchoolPortalNavLinks::portalCtaDefaults(),
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->school),
            'defaultNavConfig' => $defaults,
            'navLayoutOptions' => NavConfigDefaults::layoutOptions('school'),
            'navNeedsSetup' => empty($navConfig['items']),
            'mediaUrls' => $mediaUrls,
            'isSuperAdmin' => (bool) request()->user()?->isSuperAdmin(),
            'navMenu' => $navMenuItems,
            'hubLinks' => [
                'hub' => "/school-admin/{$this->school->id}/website",
            ],
        ]);
    }
}
