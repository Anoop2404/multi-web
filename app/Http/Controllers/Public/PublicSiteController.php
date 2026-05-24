<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicSiteController extends Controller
{
    public function home(Request $request)
    {
        $tenant = tenancy()->tenant;

        abort_if(!$tenant || !$tenant->is_active, 404);

        $cacheKey = "site:{$tenant->id}:home";

        $data = Cache::remember($cacheKey, now()->addHour(), function () use ($tenant) {
            $sections = $tenant->sections()->active()->get();
            $navConfig = $tenant->settings()->where('key', 'nav_config')->first()?->value ?? [];
            $footerConfig = $tenant->settings()->where('key', 'footer_config')->first()?->value ?? [];
            $theme = $tenant->settings()->where('key', 'theme')->first()?->value ?? [];

            return compact('sections', 'navConfig', 'footerConfig', 'theme');
        });

        return view('public.home', array_merge($data, [
            'tenant' => $tenant,
            'tenantTheme' => $data['theme'],
        ]));
    }
}
