<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Public\PublicSiteController;
use App\Http\Controllers\Public\RegistrationLandingController;
use App\Http\Middleware\SetPublicCacheHeaders;
use App\Support\TenantDomainSync;
use App\Support\TenantRequestResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers host-aware routes after tenant routes so central domains work.
 */
class CentralRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            Route::middleware(['web', SetPublicCacheHeaders::class])->group(function () {
                Route::get('/', function (Request $request) {
                    $host = strtolower($request->getHost());

                    if (TenantDomainSync::isCentralHost($host)) {
                        if (! auth()->check()) {
                            return redirect()->route('login');
                        }

                        return redirect()->to(AuthController::homeFor(auth()->user()));
                    }

                    try {
                        TenantRequestResolver::initializeFromRequest($request);
                    } catch (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException) {
                        abort(404, 'This portal domain is not registered. Ask superadmin to save the Sahodaya domain again, or run: php artisan tenants:sync-domains');
                    }

                    if (\App\Support\TenantPublicSite::isEnabled(tenancy()->tenant)) {
                        return app(PublicSiteController::class)->home($request);
                    }

                    return app(RegistrationLandingController::class)($request);
                });

                Route::redirect('/admin', '/admin/dashboard');
            });
        });
    }
}
