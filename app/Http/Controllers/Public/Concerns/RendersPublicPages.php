<?php

namespace App\Http\Controllers\Public\Concerns;

use App\Models\Tenant;
use App\Support\TenantBranding;
use App\Support\TenantCache;
use Illuminate\Http\Response;

trait RendersPublicPages
{
    protected function resolveTenant(): Tenant
    {
        $tenant = tenancy()->tenant;

        abort_if(! $tenant || ! $tenant->is_active, 404);

        return $tenant;
    }

    protected function layoutData(Tenant $tenant): array
    {
        return $this->tenantCacheRemember(
            $tenant,
            'site:layout',
            now()->addHour(),
            fn () => [
                'navConfig'    => $tenant->settings()->where('key', 'nav_config')->first()?->value ?? [],
                'footerConfig' => $tenant->settings()->where('key', 'footer_config')->first()?->value ?? [],
                'theme'        => $tenant->settings()->where('key', 'theme')->first()?->value ?? [],
                'widgets'      => $tenant->settings()->where('key', 'widgets')->first()?->value ?? [],
                'seo'          => $tenant->settings()->where('key', 'seo')->first()?->value ?? [],
                'locale'       => $tenant->settings()->where('key', 'locale')->first()?->value ?? 'en',
                'logo'         => TenantBranding::logoUrl($tenant),
            ]
        );
    }

    protected function tenantCacheRemember(Tenant $tenant, string $key, $ttl, callable $callback): mixed
    {
        return TenantCache::remember($tenant->id, $key, $ttl, $callback);
    }

    protected function renderPublic(string $view, Tenant $tenant, array $extra = []): Response
    {
        $layout = $this->layoutData($tenant);
        $widgets = $layout['widgets'] ?? [];

        if ($widgets['visitor_counter']['active'] ?? false) {
            $count = TenantCache::store()->increment("tenant:{$tenant->id}:visitors");
            $widgets['visitor_counter']['count'] = $count;
            $layout['widgets'] = $widgets;
        }

        return response()->view($view, array_merge($layout, $extra, [
            'tenant'      => $tenant,
            'tenantTheme' => $layout['theme'],
        ]));
    }
}
