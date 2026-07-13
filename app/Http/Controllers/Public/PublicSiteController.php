<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use Illuminate\Http\Request;

class PublicSiteController extends Controller
{
    use RendersPublicPages;

    public function home(Request $request)
    {
        $tenant = $this->resolveTenant();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $tenant->sections()->forPublic()->get(),
        ]);
    }

    public function preview(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $tenant = $this->resolveTenant();
        abort_unless(auth()->user()?->tenant_id === $tenant->id || auth()->user()?->can('website.manage'), 403);

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $tenant->sections()->active()->orderBy('display_order')->get(),
            'previewMode' => true,
        ]);
    }

    public function microsite(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $site = \App\Models\WebsiteSite::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_primary', false)
            ->firstOrFail();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $tenant->sections()->forPublic()->where('site_id', $site->id)->get(),
            'microsite' => $site,
            'pageSeo' => $site->seo_json ?? [],
        ]);
    }
}
