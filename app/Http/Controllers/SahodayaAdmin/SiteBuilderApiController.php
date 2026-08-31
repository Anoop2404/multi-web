<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Admin\BuilderApiController;
use App\Support\CkscSiteTemplate;
use App\Support\NavConfigDefaults;
use App\Support\PortalNavLinks;
use App\Support\SahodayaSiteBuilderCatalog;
use App\Support\SahodayaTenantBranding;
use App\Support\TenantPublicSite;
use App\Support\SahodayaWebsiteTemplateCatalog;
use App\Services\Licensing\FeatureGate;
use App\Services\Website\SahodayaContentReadiness;
use App\Services\Website\SahodayaTemplateApplier;
use App\Models\SiteSection;
use App\Models\WebsiteSite;
use App\Models\WebsiteSiteVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sahodaya-scoped site builder API (sections, nav, footer).
 */
class SiteBuilderApiController extends SahodayaAdminController
{
    public function experiences(): JsonResponse
    {
        $premiumAllowed = app(FeatureGate::class)->allows($this->sahodaya, 'module.website_premium');

        $experiences = collect(SahodayaWebsiteTemplateCatalog::summaries())
            ->map(fn (array $exp) => $exp + ['locked' => $exp['key'] === 'sahodaya-premium' && ! $premiumAllowed])
            ->values()
            ->all();

        return response()->json(['experiences' => $experiences]);
    }

    public function applyExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $this->assertSuperAdmin();

        $data = $request->validate([
            'site_id' => 'required|integer',
            'template_key' => 'required|string|max:80',
            'mode' => 'nullable|in:full,style',
        ]);
        $this->assertAllowedTemplate($data['template_key']);

        $site = WebsiteSite::resolveForTenant($this->sahodaya->id, (int) $data['site_id']);
        $template = SahodayaWebsiteTemplateCatalog::get($data['template_key']);
        $context = SahodayaTenantBranding::context($this->sahodaya);
        $draft = $applier->applyDraft($this->sahodaya, $site, $data['template_key'], $template, $context, $data['mode'] ?? 'full');
        $this->sahodaya->invalidateCache();

        return response()->json([
            'saved' => true,
            'draft' => $draft,
            'readiness' => app(SahodayaContentReadiness::class)->inspect($this->sahodaya, $site->fresh()),
        ]);
    }

    public function cancelExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $this->assertSuperAdmin();

        $site = $this->requestSite($request);
        $applier->cancelDraft($site);
        $this->sahodaya->invalidateCache();

        return response()->json(['cancelled' => true]);
    }

    public function publishExperienceDraft(Request $request, SahodayaTemplateApplier $applier, SahodayaContentReadiness $readiness): JsonResponse
    {
        $this->assertSuperAdmin();

        $site = $this->requestSite($request);
        $report = $readiness->inspect($this->sahodaya, $site);
        if (! $report['ready']) {
            return response()->json(['message' => 'Resolve the blocking readiness items before publishing.', 'readiness' => $report], 422);
        }

        $site = $applier->publishDraft($site);
        $this->sahodaya->invalidateCache();

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
        $this->assertSuperAdmin();

        $site = $this->requestSite($request);
        $version = WebsiteSiteVersion::where('website_site_id', $site->id)->findOrFail($versionId);
        $site = app(SahodayaTemplateApplier::class)->restore($site, $version);
        $this->sahodaya->invalidateCache();

        return response()->json([
            'restored' => true,
            'site' => $this->sitePayload($site),
            'sections' => $site->sectionQuery()->orderBy('display_order')->get(),
        ]);
    }

    public function readiness(Request $request, SahodayaContentReadiness $readiness): JsonResponse
    {
        return response()->json($readiness->inspect($this->sahodaya, $this->requestSite($request)));
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
            'navigation' => 'nullable|in:directory,event,editorial,institutional',
            'footer' => 'nullable|in:directory,event,editorial,institutional',
            'homepage_mode' => 'nullable|in:evergreen,registration_open,event_live,results_published',
            'homepage_mode_override_until' => 'nullable|date|after:now',
        ]);
        unset($data['site_id']);
        $homepageMode = $data['homepage_mode'] ?? $site->homepage_mode;
        $overrideUntil = $data['homepage_mode_override_until'] ?? null;
        unset($data['homepage_mode']);
        unset($data['homepage_mode_override_until']);
        $site->update(['design_json' => $data, 'homepage_mode' => $homepageMode, 'homepage_mode_override_until' => $overrideUntil]);
        $this->sahodaya->invalidateCache();

        return response()->json(['saved' => true, 'design' => $data, 'homepage_mode' => $homepageMode]);
    }

    public function sections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->sections($request, $this->sahodaya->id);
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
        return app(BuilderApiController::class)->deleteSection(request(), $this->sahodaya->id, $sectionId);
    }

    public function toggleSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->toggleSection(request(), $this->sahodaya->id, $sectionId);
    }

    public function reorderSections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->reorderSections($request, $this->sahodaya->id);
    }

    public function duplicateSection(Request $request, string $tenantId, int $sectionId): JsonResponse
    {
        $site = $this->requestSite($request);
        $source = $site->sectionQuery()->findOrFail($sectionId);
        $copy = $source->replicate(['published_config', 'published_layout_json', 'published_at']);
        $copy->site_id = $site->id;
        $copy->display_order = ((int) $site->sectionQuery()->max('display_order')) + 1;
        $copy->status = SiteSection::STATUS_DRAFT;
        $copy->is_active = false;
        $copy->updated_by = auth()->id();
        $copy->save();
        $copy->recordVersion('Duplicated from section #'.$source->id);

        return response()->json($copy->fresh(), 201);
    }

    public function publishSection(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->publishSection(request(), $this->sahodaya->id, $sectionId);
    }

    public function sectionVersions(string $tenantId, int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->sectionVersions(request(), $this->sahodaya->id, $sectionId);
    }

    public function restoreSectionVersion(string $tenantId, int $sectionId, int $versionId): JsonResponse
    {
        return app(BuilderApiController::class)->restoreSectionVersion(request(), $this->sahodaya->id, $sectionId, $versionId);
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
        $this->assertSuperAdmin();

        $site = \App\Models\WebsiteSite::resolveForTenant(
            $this->sahodaya->id,
            $request->filled('site_id') ? $request->integer('site_id') : null,
        );
        abort_unless($site->is_primary, 422, 'The legacy CKSC template can only be applied to the primary website.');

        $replace = $request->boolean('replace_sections', true);

        \App\Support\TenancyDatabase::runWhenDatabaseReady($this->sahodaya, function () use ($replace) {
            CkscSiteTemplate::apply($this->sahodaya, $replace);
        });

        $this->sahodaya->invalidateCache();

        $nav = $this->sahodaya->getSetting('nav_config', []);
        $sections = $site->sectionQuery()->orderBy('display_order')->get();

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

    public function setExperienceVersion(Request $request, SahodayaTemplateApplier $applier, SahodayaContentReadiness $readiness): JsonResponse
    {
        $this->assertSuperAdmin();

        $data = $request->validate([
            'site_id'            => 'required|integer',
            'experience_version' => 'required|in:v1,v2',
        ]);

        $site = WebsiteSite::resolveForTenant($this->sahodaya->id, (int) $data['site_id']);

        if ($data['experience_version'] === 'v2' && ! $site->sections()->exists()) {
            // No section actually scoped to this site yet — apply a real template (same
            // mechanism the Experience tab's "Apply" + "Publish" flow uses) instead of just
            // flipping the flag and leaving visitors on the legacy fallback page.
            $templateKey = $site->template_key && array_key_exists($site->template_key, SahodayaWebsiteTemplateCatalog::all())
                ? $site->template_key
                : 'network-directory';
            $template = SahodayaWebsiteTemplateCatalog::get($templateKey);
            $context = SahodayaTenantBranding::context($this->sahodaya);
            $applier->applyDraft($this->sahodaya, $site, $templateKey, $template, $context);

            $report = $readiness->inspect($this->sahodaya, $site->fresh());
            if (! $report['ready']) {
                $applier->cancelDraft($site);

                return response()->json([
                    'message' => 'Could not switch to the Modern (V2) website yet: '.implode(' ', $report['errors']),
                ], 422);
            }

            $applier->publishDraft($site);
        } else {
            $site->update(['experience_version' => $data['experience_version']]);
        }

        $this->sahodaya->invalidateCache();

        return response()->json([
            'saved'              => true,
            'experience_version' => $site->fresh()->experience_version,
            'site'               => $this->sitePayload($site->fresh()),
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

    private function assertAllowedTemplate(string $templateKey): void
    {
        if ($templateKey === 'sahodaya-premium' && ! app(FeatureGate::class)->allows($this->sahodaya, 'module.website_premium')) {
            throw ValidationException::withMessages([
                'template_key' => 'Upgrade to the Premium plan to apply this website design.',
            ]);
        }
    }

    /**
     * Choosing/publishing/rolling back which template or design version a Sahodaya's
     * site runs is a platform-assigned decision, not self-service — Sahodaya admins
     * manage section content only. Superadmin still reaches these same endpoints
     * (impersonating or navigating directly into this Sahodaya's own admin panel).
     */
    private function assertSuperAdmin(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403, 'Only a platform administrator can assign the website template or version.');
    }

    private function requestSite(Request $request): WebsiteSite
    {
        $data = $request->validate(['site_id' => 'required|integer']);

        return WebsiteSite::resolveForTenant($this->sahodaya->id, (int) $data['site_id']);
    }

    /** @return array<string, mixed> */
    private function sitePayload(WebsiteSite $site): array
    {
        return $site->only([
            'id', 'name', 'slug', 'is_primary', 'is_active', 'template_key',
            'template_version', 'experience_version', 'homepage_mode', 'homepage_mode_override_until', 'design_json', 'draft_template_json',
        ]);
    }
}
