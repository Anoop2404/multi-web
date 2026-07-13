<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Admin\BuilderApiController;
use App\Support\NavConfigDefaults;
use App\Support\SchoolPortalNavLinks;
use App\Support\SchoolSiteBuilderCatalog;
use App\Support\TenantPublicSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteBuilderApiController extends SchoolAdminController
{
    public function sections(): JsonResponse
    {
        return app(BuilderApiController::class)->sections($this->school->id);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $this->assertAllowedSection($request->input('section_type'), $request->input('variant'));

        return app(BuilderApiController::class)->storeSection($request, $this->school->id);
    }

    public function updateSection(Request $request, int $sectionId): JsonResponse
    {
        if ($request->filled('section_type') || $request->filled('variant')) {
            $this->assertAllowedSection(
                $request->input('section_type'),
                $request->input('variant')
            );
        }

        return app(BuilderApiController::class)->updateSection($request, $this->school->id, $sectionId);
    }

    public function deleteSection(int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->deleteSection($this->school->id, $sectionId);
    }

    public function toggleSection(int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->toggleSection($this->school->id, $sectionId);
    }

    public function reorderSections(Request $request): JsonResponse
    {
        return app(BuilderApiController::class)->reorderSections($request, $this->school->id);
    }

    public function publishSection(int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->publishSection($this->school->id, $sectionId);
    }

    public function sectionVersions(int $sectionId): JsonResponse
    {
        return app(BuilderApiController::class)->sectionVersions($this->school->id, $sectionId);
    }

    public function restoreSectionVersion(int $sectionId, int $versionId): JsonResponse
    {
        return app(BuilderApiController::class)->restoreSectionVersion($this->school->id, $sectionId, $versionId);
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
