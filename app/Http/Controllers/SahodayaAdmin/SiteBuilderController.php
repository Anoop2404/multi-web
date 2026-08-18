<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\WebsiteSite;
use App\Support\NavConfigDefaults;
use App\Support\PortalNavLinks;
use App\Support\SahodayaSiteBuilderCatalog;
use App\Support\SectionFieldRegistry;
use App\Support\SahodayaTenantBranding;
use App\Support\TenantPublicSite;
use App\Support\SahodayaWebsiteTemplateCatalog;
use App\Services\Website\SahodayaContentReadiness;
use Illuminate\Http\Request;

class SiteBuilderController extends SahodayaAdminController
{
    public function index(Request $request): \Inertia\Response
    {
        $sites = WebsiteSite::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        if ($sites->where('is_primary', true)->isEmpty()) {
            WebsiteSite::ensurePrimary($this->sahodaya->id);
            $sites = WebsiteSite::query()
                ->where('tenant_id', $this->sahodaya->id)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get();
        }

        $site = WebsiteSite::resolveForTenant(
            $this->sahodaya->id,
            $request->filled('site_id') ? $request->integer('site_id') : null,
        );

        $sections = $site->sectionQuery()
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
            'sites'         => $sites->map(fn (WebsiteSite $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'slug' => $candidate->slug,
                'is_primary' => $candidate->is_primary,
                'is_active' => $candidate->is_active,
                'template_key' => $candidate->template_key,
                'experience_version' => $candidate->experience_version,
                'has_draft' => ! empty($candidate->draft_template_json),
            ])->values(),
            'currentSite'   => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'is_primary' => $site->is_primary,
                'is_active' => $site->is_active,
                'template_key' => $site->template_key,
                'template_version' => $site->template_version,
                'experience_version' => $site->experience_version,
                'homepage_mode' => $site->homepage_mode,
                'homepage_mode_override_until' => $site->homepage_mode_override_until?->format('Y-m-d\TH:i'),
                'design_json' => $site->design_json ?? [],
                'draft_template_json' => $site->draft_template_json,
            ],
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
            'experiences'            => SahodayaWebsiteTemplateCatalog::summaries(),
            'readiness'              => app(SahodayaContentReadiness::class)->inspect($this->sahodaya, $site),
        ]);
    }
}
