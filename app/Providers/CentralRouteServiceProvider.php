<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Public\PublicSiteController;
use App\Http\Controllers\Public\RegistrationLandingController;
use App\Http\Middleware\SetPublicCacheHeaders;
use App\Support\FeatureFlags;
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

                    TenantRequestResolver::initializeFromRequest($request);

                    if (FeatureFlags::websiteEnabled()) {
                        return app(PublicSiteController::class)->home($request);
                    }

                    return app(RegistrationLandingController::class)($request);
                });

                Route::redirect('/admin', '/admin/dashboard');
            });
        });
    }
}
