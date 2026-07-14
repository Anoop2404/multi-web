<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Admin\BuilderApiController;
use App\Support\CkscSiteTemplate;
use App\Support\NavConfigDefaults;
use App\Support\PortalNavLinks;
use App\Support\SahodayaSiteBuilderCatalog;
use App\Support\SahodayaTenantBranding;
use App\Support\TenantPublicSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sahodaya-scoped site builder API (sections, nav, footer).
 */
class SiteBuilderApiController extends SahodayaAdminController
{
    public function sections(): JsonResponse
    {
        return app(BuilderApiController::class)->sections($this->sahodaya->id);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $this->assertAllowedSection($request->input('section_type'), $request->input('variant'));

        return app(BuilderApiController::class)->storeSection($request, $this->sahodaya->id);
    }

    public function updateSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        if ($request->filled('section_type') || $request->filled('variant')) {
            $this->assertAllowedSection(
                $request->input('section_type'),
                $request->input('variant')
            );
        }

        return app(BuilderApiController::class)->updateSection($request, $this->sahodaya->id, $sectionId);
    }

    public function deleteSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->deleteSection($this->sahodaya->id, $sectionId);
    }

    public function toggleSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->toggleSection($this->sahodaya->id, $sectionId);
    }

    public function reorderSections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->reorderSections($request, $this->sahodaya->id);
    }

    public function publishSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->publishSection($this->sahodaya->id, $sectionId);
    }

    public function sectionVersions(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->sectionVersions($this->sahodaya->id, $sectionId);
    }

    public function restoreSectionVersion(string $tenantId, int $sectionId, int $versionId): JsonResponse
    {
        return app(BuilderApiController::class)->restoreSectionVersion($this->sahodaya->id, $sectionId, $versionId);
    }

    public function getNav(): JsonResponse
    {
        $config = $this->sahodaya->getSetting('nav_config', []);
        $config['portal_cta'] = array_merge(
            PortalNavLinks::portalCtaDefaults(),
            $config['portal_cta'] ?? []
        );

        return response()->json($config);
    }

    public function saveNav(Request $request): JsonResponse
    {
        $data = $request->validate([
            'style'          => 'nullable|string|max:50',
            'layout_variant' => 'nullable|string|max:50',
            'items'          => 'nullable|array',
            'items.*.label'  => 'required_with:items|string|max:100',
            'items.*.url'    => 'required_with:items|string|max:500',
            'items.*.children' => 'nullable|array',
            'portal_cta'     => 'nullable|array',
            'portal_cta.show_in_navbar' => 'nullable|boolean',
            'portal_cta.show_in_menu'   => 'nullable|boolean',
            'portal_cta.portal_label'   => 'nullable|string|max:100',
            'portal_cta.portal_url'     => 'nullable|string|max:500',
            'portal_cta.register_label' => 'nullable|string|max:100',
            'portal_cta.register_url'   => 'nullable|string|max:500',
            'portal_cta.login_label'    => 'nullable|string|max:100',
            'portal_cta.login_url'      => 'nullable|string|max:500',
        ]);

        $data = PortalNavLinks::mergePortalCta($data);

        $variant = $data['layout_variant'] ?? $data['style'] ?? 'sahodaya-modern';
        $data['style'] = $variant;
        $data['layout_variant'] = $variant;

        $this->sahodaya->setSetting('nav_config', $data);

        return response()->json(['saved' => true, 'nav' => $data]);
    }

    public function getFooter(): JsonResponse
    {
        return response()->json($this->sahodaya->getSetting('footer_config', []));
    }

    public function saveFooter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout_variant'        => 'nullable|string|max:50',
            'tagline'               => 'nullable|string|max:500',
            'copyright'             => 'nullable|string|max:500',
            'phone'                 => 'nullable|string|max:50',
            'email'                 => 'nullable|email|max:255',
            'quick_links'           => 'nullable|array',
            'quick_links.*.label'   => 'required_with:quick_links|string|max:100',
            'quick_links.*.url'     => 'required_with:quick_links|string|max:500',
            'include_portal_links'  => 'nullable|boolean',
        ]);

        if ($request->boolean('include_portal_links', true)) {
            $data = PortalNavLinks::ensureFooterLinks($data);
        }

        $this->sahodaya->setSetting('footer_config', $data);

        return response()->json(['saved' => true, 'footer' => $data]);
    }

    public function ensurePortalLinks(): JsonResponse
    {
        $nav = PortalNavLinks::mergePortalCta($this->sahodaya->getSetting('nav_config', []));
        $nav['portal_cta']['show_in_navbar'] = true;
        $nav['portal_cta']['show_in_menu'] = true;
        $this->sahodaya->setSetting('nav_config', $nav);

        $footer = PortalNavLinks::ensureFooterLinks($this->sahodaya->getSetting('footer_config', []));
        $this->sahodaya->setSetting('footer_config', $footer);

        return response()->json([
            'saved'  => true,
            'nav'    => $nav,
            'footer' => $footer,
        ]);
    }

    public function ensureDefaultNav(): JsonResponse
    {
        $nav = PortalNavLinks::mergePortalCta(NavConfigDefaults::forSahodaya());
        $this->sahodaya->setSetting('nav_config', $nav);

        $footer = PortalNavLinks::ensureFooterLinks($this->sahodaya->getSetting('footer_config', []));
        $this->sahodaya->setSetting('footer_config', $footer);

        return response()->json([
            'saved'  => true,
            'nav'    => $nav,
            'footer' => $footer,
        ]);
    }

    public function applyCkscTemplate(Request $request): JsonResponse
    {
        $replace = $request->boolean('replace_sections', true);

        \App\Support\TenancyDatabase::runWhenDatabaseReady($this->sahodaya, function () use ($replace) {
            CkscSiteTemplate::apply($this->sahodaya, $replace);
        });

        $this->sahodaya->invalidateCache();

        $nav = $this->sahodaya->getSetting('nav_config', []);
        $sections = $this->sahodaya->sections()->orderBy('display_order')->get();

        return response()->json([
            'saved'    => true,
            'nav'      => $nav,
            'sections' => $sections,
            'message'  => 'CKSC website template applied (pill menu, hero slider, homepage sections, CMS pages).',
        ]);
    }

    public function getPublicWebsite(): JsonResponse
    {
        return response()->json([
            'enabled' => TenantPublicSite::isEnabled($this->sahodaya),
        ]);
    }

    public function savePublicWebsite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        TenantPublicSite::setEnabled($this->sahodaya, $data['enabled']);

        return response()->json([
            'saved'   => true,
            'enabled' => $data['enabled'],
        ]);
    }

    public function getTheme(): JsonResponse
    {
        return response()->json([
            'theme'   => SahodayaTenantBranding::theme($this->sahodaya),
            'presets' => SahodayaTenantBranding::themePresets(),
        ]);
    }

    public function saveTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'primary'       => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary'     => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color'  => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_heading'  => 'nullable|string|max:50',
            'font_body'     => 'nullable|string|max:50',
        ]);

        $theme = SahodayaTenantBranding::saveTheme($this->sahodaya, $data);
        $this->sahodaya->invalidateCache();

        return response()->json(['saved' => true, 'theme' => $theme]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ]);

        $path = \App\Support\TenantStorage::storeSiteMedia(
            $request->file('file'),
            $this->sahodaya->id
        );

        $url = \App\Support\TenantStorage::siteMediaUrl($this->sahodaya, $path);

        return response()->json([
            'path' => $path,
            'url'  => $url,
        ]);
    }

    private function assertAllowedSection(?string $sectionType, ?string $variant): void
    {
        if ($sectionType && ! SahodayaSiteBuilderCatalog::allows($sectionType, $variant)) {
            throw ValidationException::withMessages([
                'section_type' => 'This section type is not available in the Sahodaya site builder.',
            ]);
        }
    }
}
