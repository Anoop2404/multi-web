<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Admin\BuilderApiController;
use App\Http\Controllers\Traits\HandlesRepeaterItems;
use App\Models\SiteSection;
use App\Models\WebsiteSite;
use App\Models\WebsiteSiteVersion;
use App\Services\Website\SahodayaTemplateApplier;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolPublicPageContent;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\SchoolWebsiteTemplateCatalog;
use App\Support\TenantPublicSite;
use App\Support\TenantDomainSync;
use App\Support\TenantStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteBuilderApiController extends SchoolAdminController
{
    use HandlesRepeaterItems;
    public function experiences(): JsonResponse
    {
        return response()->json(['experiences' => SchoolWebsiteTemplateCatalog::summaries()]);
    }

    public function applyExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $this->assertSuperAdmin();

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
        $this->assertSuperAdmin();

        $site = $this->requestSite($request);
        $applier->cancelDraft($site);
        $this->school->invalidateCache();

        return response()->json(['cancelled' => true]);
    }

    public function publishExperienceDraft(Request $request, SahodayaTemplateApplier $applier): JsonResponse
    {
        $this->assertSuperAdmin();

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
        $this->assertSuperAdmin();

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

    public function getDesign(): JsonResponse
    {
        $site = WebsiteSite::ensurePrimary($this->school->id);

        return response()->json([
            'site' => $this->sitePayload($site),
            'design' => $site->design_config ?? [],
        ]);
    }

    public function saveDesign(Request $request): JsonResponse
    {
        $this->assertSuperAdmin();

        $site = $this->requestSite($request);
        $data = $request->validate([
            'site_id' => 'required|integer',
            'primary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'page_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'muted_surface' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'hero_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'navbar_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'footer_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'footer_text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
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

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ]);

        $path = TenantStorage::storeSiteMedia($request->file('file'), $this->school->id);

        return response()->json([
            'path' => $path,
            'url' => TenantStorage::siteMediaUrl($this->school, $path),
        ]);
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
        $this->assertSuperAdmin();

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
        $this->assertSuperAdmin();

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

    public function getMenu(): JsonResponse
    {
        $site = WebsiteSite::ensurePrimary($this->school->id);
        $sections = $site->sectionQuery()
            ->orderBy('display_order')
            ->get(['id', 'section_type', 'variant', 'is_active', 'show_in_menu', 'display_order']);

        $items = $sections->map(fn ($s) => [
            'id' => $s->id,
            'section_type' => $s->section_type,
            'label' => NavConfigDefaults::sectionLabel($s->section_type),
            'variant' => $s->variant,
            'is_active' => $s->is_active,
            'show_in_menu' => (bool) $s->show_in_menu,
            'display_order' => $s->display_order,
        ])->values()->all();

        return response()->json(['items' => $items]);
    }

    public function saveMenu(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|distinct',
            'items.*.show_in_menu' => 'required|boolean',
            'items.*.display_order' => 'required|integer|min:0',
        ]);

        $ids = collect($data['items'])->pluck('id')->all();
        $site = WebsiteSite::ensurePrimary($this->school->id);
        $allowedIds = $site->sectionQuery()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        abort_unless(count($allowedIds) === count($ids), 422, 'All menu items must belong to the primary website.');

        DB::transaction(function () use ($data, $site) {
            foreach ($data['items'] as $item) {
                $site->sectionQuery()->whereKey($item['id'])->update([
                    'show_in_menu' => $item['show_in_menu'],
                    'display_order' => $item['display_order'],
                ]);
            }
        });

        $this->school->invalidateCache();

        return response()->json(['saved' => true]);
    }

    public function syncMenu(): JsonResponse
    {
        $site = WebsiteSite::ensurePrimary($this->school->id);
        $sections = $site->sectionQuery()
            ->orderBy('display_order')
            ->get(['id', 'section_type', 'variant', 'is_active', 'show_in_menu', 'display_order']);

        $items = $sections->map(fn ($s) => [
            'id' => $s->id,
            'section_type' => $s->section_type,
            'label' => NavConfigDefaults::sectionLabel($s->section_type),
            'variant' => $s->variant,
            'is_active' => $s->is_active,
            'show_in_menu' => (bool) $s->show_in_menu,
            'display_order' => $s->display_order,
        ])->values()->all();

        return response()->json(['items' => $items]);
    }

    public function saveNav(Request $request): JsonResponse
    {
        $data = $request->validate([
            'style' => 'nullable|string|max:50',
            'layout_variant' => 'nullable|string|max:50',
            'items' => 'nullable|array',
            'items.*.label' => 'required_with:items|string|max:100',
            'items.*.url' => 'required_with:items|string|max:500',
            'items.*.children' => 'nullable|array',
            'portal_cta' => 'nullable|array',
            'portal_cta.show_in_navbar' => 'nullable|boolean',
            'portal_cta.show_in_menu' => 'nullable|boolean',
            'portal_cta.register_label' => 'nullable|string|max:100',
            'portal_cta.register_url' => 'nullable|string|max:500',
            'portal_cta.login_label' => 'nullable|string|max:100',
            'portal_cta.login_url' => 'nullable|string|max:500',
            'portal_cta.cbse_btn' => 'nullable|boolean',
            'portal_cta.cbse_label' => 'nullable|string|max:100',
            'portal_cta.cbse_url' => 'nullable|string|max:500',
            'portal_cta.contact_btn' => 'nullable|boolean',
            'portal_cta.contact_label' => 'nullable|string|max:100',
            'portal_cta.contact_url' => 'nullable|string|max:500',
            'items.*.external' => 'nullable|boolean',
            'items.*.children.*.label' => 'required_with:items.*.children|string|max:100',
            'items.*.children.*.url' => 'required_with:items.*.children|string|max:500',
            'items.*.children.*.external' => 'nullable|boolean',
        ]);

        $data = SchoolPortalNavLinks::mergePortalCta($data);

        $variant = $data['layout_variant'] ?? $data['style'] ?? 'logo-left';
        $data['style'] = $variant;
        $data['layout_variant'] = $variant;

        $this->school->setSetting('nav_config', $data);
        $this->school->invalidateCache();

        return response()->json(['saved' => true, 'nav' => $data]);
    }

    public function getFooter(): JsonResponse
    {
        return response()->json($this->school->getSetting('footer_config', []));
    }

    public function saveFooter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout_variant' => 'nullable|string|max:50',
            'tagline' => 'nullable|string|max:500',
            'copyright' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'quick_links_heading' => 'nullable|string|max:100',
            'contact_heading' => 'nullable|string|max:100',
            'quick_links' => 'nullable|array',
            'quick_links.*.label' => 'required_with:quick_links|string|max:100',
            'quick_links.*.url' => 'required_with:quick_links|string|max:500',
            'include_portal_links' => 'nullable|boolean',
        ]);

        if ($request->boolean('include_portal_links', true)) {
            $data = SchoolPortalNavLinks::ensureFooterLinks($data);
        }

        $this->school->setSetting('footer_config', $data);
        $this->school->invalidateCache();

        return response()->json(['saved' => true, 'footer' => $data]);
    }

    public function getSiteContent(): JsonResponse
    {
        return response()->json([
            'content' => SchoolPublicPageContent::resolve($this->school),
        ]);
    }

    public function saveSiteContent(Request $request): JsonResponse
    {
        $this->requestSite($request);

        $rules = ['site_id' => 'required|integer'];
        foreach ($this->siteContentStringPaths() as $path => $max) {
            $rules[$path] = "nullable|string|max:{$max}";
        }

        $data = $request->validate($rules);
        unset($data['site_id']);

        $this->school->setSetting('site_content', $data);
        $this->school->invalidateCache();

        return response()->json([
            'saved' => true,
            'content' => SchoolPublicPageContent::resolve($this->school),
        ]);
    }

    /**
     * Return every public page with its editable text, sections, and nav links.
     *
     * @return array<string, mixed>
     */
    public function getPages(): JsonResponse
    {
        $site = WebsiteSite::ensurePrimary($this->school->id);
        $sections = $site->sectionQuery()
            ->orderBy('display_order')
            ->get(['id', 'section_type', 'variant', 'is_active', 'show_in_menu', 'display_order', 'config']);

        $navConfig = NavConfigDefaults::resolve($this->school, $this->school->getSetting('nav_config', []));
        $siteContent = SchoolPublicPageContent::resolve($this->school);
        $publicUrl = TenantDomainSync::publicUrl($this->school);

        $pages = [];
        foreach ($this->pageDefinitions() as $page) {
            $pageSections = $this->resolvePageSections($page['slug'], $sections);
            $navLinks = $this->resolveNavLinksForPage($page['slug'], $navConfig);

            $pages[] = [
                'slug' => $page['slug'],
                'label' => $page['label'],
                'icon' => $page['icon'],
                'url' => $page['url'],
                'is_home' => $page['slug'] === 'home',
                'enabled' => $page['slug'] === 'home'
                    ? true
                    : $pageSections->isNotEmpty(),
                'content' => $siteContent['pages'][$page['slug']] ?? [],
                'sections' => $pageSections->map(fn ($s) => [
                    'id' => $s->id,
                    'section_type' => $s->section_type,
                    'label' => NavConfigDefaults::sectionLabel($s->section_type),
                    'variant' => $s->variant,
                    'is_active' => $s->is_active,
                    'show_in_menu' => $s->show_in_menu,
                    'preview' => $s->config['heading'] ?? $s->config['title'] ?? $s->config['tagline'] ?? null,
                ])->values()->all(),
                'nav_links' => $navLinks,
            ];
        }

        return response()->json([
            'pages' => $pages,
            'hero_section_id' => $sections->firstWhere('section_type', 'hero')?->id,
            'navbar_style' => $navConfig['layout_variant'] ?? $navConfig['style'] ?? 'logo-left',
        ]);
    }

    /**
     * Update the editable text for a single public page.
     */
    public function savePageText(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string',
            'title' => 'nullable|string|max:200',
            'eyebrow' => 'nullable|string|max:200',
            'subheading' => 'nullable|string|max:2000',
            'seo_title' => 'nullable|string|max:200',
            'seo_description' => 'nullable|string|max:500',
        ]);

        $slug = $request->input('slug');
        $allowed = collect($this->pageDefinitions())->pluck('slug')->all();
        abort_unless(in_array($slug, $allowed, true), 404);

        $content = SchoolPublicPageContent::resolve($this->school);
        $pages = $content['pages'] ?? [];
        if (!isset($pages[$slug])) {
            $pages[$slug] = [];
        }

        foreach (['title', 'eyebrow', 'subheading', 'seo_title', 'seo_description'] as $field) {
            if ($request->has($field)) {
                $pages[$slug][$field] = $request->input($field);
            }
        }

        $content['pages'] = $pages;
        $this->school->setSetting('site_content', $content);
        $this->school->invalidateCache();

        return response()->json([
            'saved' => true,
            'content' => $content,
        ]);
    }

    public function ensurePortalLinks(): JsonResponse
    {
        $nav = SchoolPortalNavLinks::mergePortalCta($this->school->getSetting('nav_config', []));
        $nav['portal_cta']['show_in_navbar'] = true;
        $nav['portal_cta']['show_in_menu'] = true;
        $this->school->setSetting('nav_config', $nav);

        $footer = SchoolPortalNavLinks::ensureFooterLinks($this->school->getSetting('footer_config', []));
        $this->school->setSetting('footer_config', $footer);
        $this->school->invalidateCache();

        return response()->json([
            'saved' => true,
            'nav' => $nav,
            'footer' => $footer,
        ]);
    }

    public function ensureDefaultNav(): JsonResponse
    {
        $nav = SchoolPortalNavLinks::mergePortalCta(NavConfigDefaults::forSchool($this->school));
        $this->school->setSetting('nav_config', $nav);

        $footer = SchoolPortalNavLinks::ensureFooterLinks($this->school->getSetting('footer_config', []));
        $this->school->setSetting('footer_config', $footer);
        $this->school->invalidateCache();

        return response()->json([
            'saved' => true,
            'nav' => $nav,
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
            'saved' => true,
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

    /** @return array<string, int> */
    private function siteContentStringPaths(): array
    {
        $paths = [
            'branding.subtitle' => 150,
            'common.back_to_home' => 100,
        ];

        foreach (SchoolPublicPageContent::defaults()['pages'] as $page => $values) {
            foreach ($values as $field => $value) {
                $paths["pages.{$page}.{$field}"] = str_contains($field, 'description') || in_array($field, ['intro', 'success_message'], true)
                    ? 500
                    : 200;
            }
        }

        return $paths;
    }

    private function assertSuperAdmin(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403, 'Template architecture and structural site changes are restricted to Platform Super Admins only.');
    }

    /**
     * @return list<array{slug: string, label: string, icon: string, url: string}>
     */
    private function pageDefinitions(): array
    {
        $baseUrl = TenantDomainSync::publicUrl($this->school);
        $baseUrl = rtrim($baseUrl ?? '/', '/');

        return [
            ['slug' => 'home', 'label' => 'Homepage', 'icon' => '🏠', 'url' => $baseUrl],
            ['slug' => 'about', 'label' => 'About Us', 'icon' => '📖', 'url' => "{$baseUrl}/about"],
            ['slug' => 'academics', 'label' => 'Academics', 'icon' => '📚', 'url' => "{$baseUrl}/academics"],
            ['slug' => 'facilities', 'label' => 'Facilities', 'icon' => '🏫', 'url' => "{$baseUrl}/facilities"],
            ['slug' => 'house-system', 'label' => 'House System', 'icon' => '🏠', 'url' => "{$baseUrl}/house-system"],
            ['slug' => 'clubs', 'label' => 'Clubs', 'icon' => '🎯', 'url' => "{$baseUrl}/clubs"],
            ['slug' => 'career-guidance', 'label' => 'Career Guidance', 'icon' => '🧭', 'url' => "{$baseUrl}/career-guidance"],
            ['slug' => 'publications', 'label' => 'Publications', 'icon' => '📄', 'url' => "{$baseUrl}/publications"],
            ['slug' => 'atl', 'label' => 'ATAL Lab', 'icon' => '🔬', 'url' => "{$baseUrl}/atl"],
            ['slug' => 'admissions', 'label' => 'Admissions', 'icon' => '📝', 'url' => "{$baseUrl}/admissions"],
            ['slug' => 'faculty', 'label' => 'Faculty', 'icon' => '👥', 'url' => "{$baseUrl}/faculty"],
            ['slug' => 'achievements', 'label' => 'Achievements', 'icon' => '⭐', 'url' => "{$baseUrl}/achievements"],
            ['slug' => 'contact', 'label' => 'Contact Us', 'icon' => '📞', 'url' => "{$baseUrl}/contact"],
            ['slug' => 'news', 'label' => 'News', 'icon' => '📰', 'url' => "{$baseUrl}/news"],
            ['slug' => 'events', 'label' => 'Events', 'icon' => '📅', 'url' => "{$baseUrl}/events"],
            ['slug' => 'gallery', 'label' => 'Gallery', 'icon' => '🖼️', 'url' => "{$baseUrl}/gallery"],
            ['slug' => 'video-gallery', 'label' => 'Video Gallery', 'icon' => '🎬', 'url' => "{$baseUrl}/video-gallery"],
            ['slug' => 'downloads', 'label' => 'Downloads', 'icon' => '📥', 'url' => "{$baseUrl}/downloads"],
            ['slug' => 'careers', 'label' => 'Careers', 'icon' => '💼', 'url' => "{$baseUrl}/careers"],
            ['slug' => 'alumni', 'label' => 'Alumni', 'icon' => '🎓', 'url' => "{$baseUrl}/alumni"],
            ['slug' => 'testimonials', 'label' => 'Testimonials', 'icon' => '💬', 'url' => "{$baseUrl}/testimonials"],
            ['slug' => 'newsletter', 'label' => 'Newsletter', 'icon' => '📧', 'url' => "{$baseUrl}/newsletter"],
            ['slug' => 'portals', 'label' => 'Quick Links', 'icon' => '🔗', 'url' => "{$baseUrl}/portals"],
            ['slug' => 'disclosure', 'label' => 'CBSE Disclosure', 'icon' => '📋', 'url' => "{$baseUrl}/disclosure"],
            ['slug' => 'results', 'label' => 'Board Results', 'icon' => '🏆', 'url' => "{$baseUrl}/results"],
            ['slug' => 'admission-enquiry', 'label' => 'Admission Enquiry', 'icon' => '📝', 'url' => "{$baseUrl}/admission-enquiry"],
        ];
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\SiteSection>  $allSections */
    private function resolvePageSections(string $pageSlug, $allSections): \Illuminate\Support\Collection
    {
        if ($pageSlug === 'home') {
            return $allSections->where('is_active', true)->sortBy('display_order');
        }

        $sectionTypeMap = [
            'about' => ['about', 'principal_message', 'management', 'about_sahodaya'],
            'facilities' => ['facilities', 'statistics'],
            'house-system' => ['house_system'],
            'clubs' => ['clubs'],
            'career-guidance' => ['career_guidance'],
            'publications' => ['publications'],
            'atl' => ['atl'],
            'admissions' => ['admissions', 'admission_enquiry'],
            'faculty' => ['staff'],
            'achievements' => ['achievements'],
            'contact' => ['contact'],
            'news' => ['news'],
            'events' => ['events'],
            'gallery' => ['gallery'],
            'video-gallery' => ['video_gallery'],
            'downloads' => ['downloads'],
            'careers' => ['job_vacancies'],
            'alumni' => ['alumni'],
            'testimonials' => ['testimonials'],
            'newsletter' => ['newsletter'],
            'portals' => ['portals'],
            'disclosure' => ['mandatory_disclosure'],
            'results' => ['board_results'],
            'academics' => ['academic_programmes'],
        ];

        $types = $sectionTypeMap[$pageSlug] ?? [];
        if ($types) {
            return $allSections
                ->whereIn('section_type', $types)
                ->where('is_active', true)
                ->sortBy('display_order');
        }

        return collect();
    }

    /** @return array{label: string, url: string}[] */
    private function resolveNavLinksForPage(string $pageSlug, array $navConfig): array
    {
        $targetUrl = null;
        foreach ($this->pageDefinitions() as $def) {
            if ($def['slug'] === $pageSlug) {
                $targetUrl = $def['url'];
                break;
            }
        }

        if (! $targetUrl) {
            return [];
        }

        $found = [];
        foreach ($navConfig['items'] ?? [] as $item) {
            if (($item['url'] ?? '') === $targetUrl) {
                $found[] = ['label' => $item['label'], 'url' => $item['url']];
            }
            foreach ($item['children'] ?? [] as $child) {
                if (($child['url'] ?? '') === $targetUrl) {
                    $found[] = ['label' => $child['label'], 'url' => $child['url']];
                }
            }
        }

        return $found;
    }

    // ── HandlesRepeaterItems trait requirements ───────────────────────────────

    private function resolveSite(Request $request, string $tenantId): WebsiteSite
    {
        $siteId = $request->filled('site_id') ? (int) $request->integer('site_id') : null;

        return WebsiteSite::resolveForTenant($tenantId, $siteId);
    }

    private function sectionForSite(WebsiteSite $site, int $sectionId): SiteSection
    {
        return $site->sectionQuery()->findOrFail($sectionId);
    }

    private function bustCache(string $tenantId): void
    {
        // School site builder does not use the same cache layer as Sahodaya.
    }
}
