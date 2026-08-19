<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Admin\BuilderApiController;
use App\Models\WebsiteSite;
use App\Models\WebsiteSiteVersion;
use App\Services\Website\SahodayaTemplateApplier;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\SchoolWebsiteTemplateCatalog;
use App\Support\TenantPublicSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteBuilderApiController extends SchoolAdminController
{
    public function experiences(): JsonResponse
    {
        return response()->json(['experiences' => SchoolWebsiteTemplateCatalog::summaries()]);
    }

    public function applyExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $data = $request->validate([
            'site_id' => 'required|integer',
            'template_key' => 'required|string|max:80',
            'mode' => 'nullable|in:full,style',
        ]);
        $site = WebsiteSite::resolveForTenant($this->school->id, (int) $data['site_id']);
        $template = SchoolWebsiteTemplateCatalog::get($data['template_key']);
        $context = ['name' => $this->school->name, 'short_name' => $this->school->name, 'region' => ''];
        $draft = $applier->applyDraft($this->school, $site, $data['template_key'], $template, $context, $data['mode'] ?? 'full');
        $this->school->invalidateCache();

        return response()->json(['saved' => true, 'draft' => $draft]);
    }

    public function cancelExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $site = $this->requestSite($request);
        $applier->cancelDraft($site);
        $this->school->invalidateCache();

        return response()->json(['cancelled' => true]);
    }

    public function publishExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $site = $this->requestSite($request);
        $site = $applier->publishDraft($site);
        $this->school->invalidateCache();

        return response()->json([
            'published' => true,
            'site' => $this->sitePayload($site),
            'sections' => $site->sectionQuery()->orderBy('display_order')->get(),
        ]);
    }

    public function experienceVersions(Request $request): JsonResponse
    {
        $site = $this->requestSite($request);

        return response()->json($site->versions()->limit(20)->get([
            'id', 'action', 'template_key', 'template_version', 'created_by', 'created_at',
        ]));
    }

    public function restoreExperienceVersion(Request $request, string $tenantId, int $versionId): JsonResponse
    {
        $site = $this->requestSite($request);
        $version = WebsiteSiteVersion::where('website_site_id', $site->id)->findOrFail($versionId);
        $site = app(SahodayaTemplateApplier::class)->restore($site, $version);
        $this->school->invalidateCache();

        return response()->json([
            'restored' => true,
            'site' => $this->sitePayload($site),
            'sections' => $site->sectionQuery()->orderBy('display_order')->get(),
        ]);
    }

    public function saveDesign(Request $request): JsonResponse
    {
        $site = $this->requestSite($request);
        $data = $request->validate([
            'site_id' => 'required|integer',
            'primary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_font' => 'required|in:Inter,Manrope,Merriweather,Roboto',
            'body_font' => 'required|in:Inter,Manrope,Roboto',
            'type_scale' => 'required|in:compact,balanced,editorial',
            'density' => 'required|in:compact,comfortable,spacious',
            'surface' => 'required|in:flat,bordered,soft,elevated',
            'corners' => 'required|in:square,soft,rounded',
            'buttons' => 'required|in:solid,bordered,understated',
            'images' => 'required|in:documentary,vibrant,formal,monochrome',
            'motion' => 'required|in:none,restrained,expressive',
            'navigation' => 'nullable|string|max:50',
            'footer' => 'nullable|string|max:50',
        ]);
        unset($data['site_id']);
        $site->update(['design_json' => $data]);
        $this->school->invalidateCache();

        return response()->json(['saved' => true, 'design' => $data]);
    }

    private function requestSite(Request $request): WebsiteSite
    {
        $data = $request->validate(['site_id' => 'required|integer']);

        return WebsiteSite::resolveForTenant($this->school->id, (int) $data['site_id']);
    }

    /** @return array<string, mixed> */
    private function sitePayload(WebsiteSite $site): array
    {
        return $site->only([
            'id', 'name', 'slug', 'is_primary', 'is_active', 'template_key',
            'template_version', 'experience_version', 'design_json', 'draft_template_json',
        ]);
    }

    public function sections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->sections($request, $this->school->id);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $this->assertAllowedSection($request->input('section_type'), $request->input('variant'));

        return app(BuilderApiController::class)->storeSection($request, $this->school->id);
    }

    public function updateSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        if ($request->filled('section_type') || $request->filled('variant')) {
            $this->assertAllowedSection(
                $request->input('section_type'),
                $request->input('variant')
            );
        }

        return app(BuilderApiController::class)->updateSection($request, $this->school->id, $sectionId);
    }

    public function deleteSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->deleteSection(request(), $this->school->id, $sectionId);
    }

    public function toggleSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->toggleSection(request(), $this->school->id, $sectionId);
    }

    public function reorderSections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->reorderSections($request, $this->school->id);
    }

    public function publishSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->publishSection(request(), $this->school->id, $sectionId);
    }

    public function sectionVersions(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->sectionVersions(request(), $this->school->id, $sectionId);
    }

    public function restoreSectionVersion(string $tenantId, int $sectionId, int $versionId): JsonResponse
    {
        return app(BuilderApiController::class)->restoreSectionVersion(request(), $this->school->id, $sectionId, $versionId);
    }

    public function getNav(): JsonResponse
    {
        $config = $this->school->getSetting('nav_config', []);
        $config['portal_cta'] = array_merge(
            SchoolPortalNavLinks::portalCtaDefaults(),
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
            'portal_cta.register_label' => 'nullable|string|max:100',
            'portal_cta.register_url'   => 'nullable|string|max:500',
            'portal_cta.login_label'    => 'nullable|string|max:100',
            'portal_cta.login_url'      => 'nullable|string|max:500',
        ]);

        $data = SchoolPortalNavLinks::mergePortalCta($data);

        $variant = $data['layout_variant'] ?? $data['style'] ?? 'logo-left';
        $data['style'] = $variant;
        $data['layout_variant'] = $variant;

        $this->school->setSetting('nav_config', $data);

        return response()->json(['saved' => true, 'nav' => $data]);
    }

    public function getFooter(): JsonResponse
    {
        return response()->json($this->school->getSetting('footer_config', []));
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
            $data = SchoolPortalNavLinks::ensureFooterLinks($data);
        }

        $this->school->setSetting('footer_config', $data);

        return response()->json(['saved' => true, 'footer' => $data]);
    }

    public function ensurePortalLinks(): JsonResponse
    {
        $nav = SchoolPortalNavLinks::mergePortalCta($this->school->getSetting('nav_config', []));
        $nav['portal_cta']['show_in_navbar'] = true;
        $nav['portal_cta']['show_in_menu'] = true;
        $this->school->setSetting('nav_config', $nav);

        $footer = SchoolPortalNavLinks::ensureFooterLinks($this->school->getSetting('footer_config', []));
        $this->school->setSetting('footer_config', $footer);

        return response()->json([
            'saved'  => true,
            'nav'    => $nav,
            'footer' => $footer,
        ]);
    }

    public function ensureDefaultNav(): JsonResponse
    {
        $nav = SchoolPortalNavLinks::mergePortalCta(NavConfigDefaults::forSchool($this->school));
        $this->school->setSetting('nav_config', $nav);

        $footer = SchoolPortalNavLinks::ensureFooterLinks($this->school->getSetting('footer_config', []));
        $this->school->setSetting('footer_config', $footer);

        return response()->json([
            'saved'  => true,
            'nav'    => $nav,
            'footer' => $footer,
        ]);
    }

    public function getPublicWebsite(): JsonResponse
    {
        return response()->json([
            'enabled' => TenantPublicSite::isEnabled($this->school),
        ]);
    }

    public function savePublicWebsite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        TenantPublicSite::setEnabled($this->school, $data['enabled']);

        return response()->json([
            'saved'   => true,
            'enabled' => $data['enabled'],
        ]);
    }

    private function assertAllowedSection(?string $sectionType, ?string $variant): void
    {
        if ($sectionType && ! SchoolSiteBuilderCatalog::allows($sectionType, $variant)) {
            throw ValidationException::withMessages([
                'section_type' => 'This section type is not available in the school site builder.',
            ]);
        }
    }
}
