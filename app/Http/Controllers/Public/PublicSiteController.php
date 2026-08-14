<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\WebsiteSite;
use Illuminate\Http\Request;
use App\Services\Website\SahodayaTemplateApplier;
use App\Support\SahodayaWebsiteTemplateCatalog;
use App\Services\Website\SahodayaHomepageModeResolver;

class PublicSiteController extends Controller
{
    use RendersPublicPages;

    public function home(Request $request)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::ensurePrimary($tenant->id);

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $site->sectionQuery()->forPublic()->get(),
            'site' => $site,
            'experience' => $this->experienceData($site),
        ]);
    }

    public function preview(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $tenant = $this->resolveTenant();
        abort_unless(auth()->user()?->tenant_id === $tenant->id || auth()->user()?->can('website.manage'), 403);
        $site = WebsiteSite::resolveForTenant(
            $tenant->id,
            $request->filled('site_id') ? $request->integer('site_id') : null,
        );

        $sections = ! empty($site->draft_template_json)
            ? app(SahodayaTemplateApplier::class)->previewSections($site)
            : $site->sectionQuery()->active()->get();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $sections,
            'previewMode' => true,
            'site' => $site,
            'microsite' => $site->is_primary ? null : $site,
            'pageSeo' => $site->seo_json ?? [],
            'experience' => $this->experienceData($site, true),
        ]);
    }

    public function microsite(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_primary', false)
            ->firstOrFail();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $tenant->sections()->forPublic()->where('site_id', $site->id)->get(),
            'microsite' => $site,
            'pageSeo' => $site->seo_json ?? [],
            'site' => $site,
            'experience' => $this->experienceData($site),
        ]);
    }

    /** @return array<string, mixed> */
    private function experienceData(WebsiteSite $site, bool $preview = false): array
    {
        $draft = $preview ? ($site->draft_template_json ?? []) : [];
        $key = $draft['template_key'] ?? $site->template_key;

        return [
            'key' => $key,
            'version' => $draft['template_version'] ?? $site->template_version,
            'experience_version' => $key ? 'v2' : ($site->experience_version ?? 'v1'),
            'homepage_mode' => app(SahodayaHomepageModeResolver::class)->resolve($site),
            'design' => $draft['design'] ?? $site->design_json ?? [],
            'widget_policy' => $draft['widgets'] ?? SahodayaWebsiteTemplateCatalog::widgetPolicy($key),
        ];
    }
}
