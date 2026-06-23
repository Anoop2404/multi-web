<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Support\SahodayaTenantBranding;
use Illuminate\Http\Response;

class SahodayaCmsPageController extends Controller
{
    use RendersPublicPages;

    public function show(string $slug): Response
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant->type === 'sahodaya', 404);

        $ctx = SahodayaTenantBranding::context($tenant);
        $pages = $tenant->getSetting('cms_pages', SahodayaTenantBranding::cmsPages($tenant));
        $page = $pages[$slug] ?? null;

        abort_if(! $page, 404);

        return $this->renderPublic('public.sahodaya.page', $tenant, [
            'page' => array_merge([
                'org_title' => $ctx['org_title'],
                'logo'      => $ctx['logo'],
            ], $page),
        ]);
    }
}
