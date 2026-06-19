<?php

declare(strict_types=1);

use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\GalleryAlbumController;
use App\Http\Controllers\Public\NewsArticleController;
use App\Http\Controllers\Public\SchoolApplicationController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Controllers\Public\TcRequestController;
use App\Http\Middleware\InitializeTenancyByRequestHost;
use App\Http\Middleware\SetPublicCacheHeaders;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByRequestHost::class,
    PreventAccessFromCentralDomains::class,
    SetPublicCacheHeaders::class,
])->group(function () {

    // Home route is registered in CentralRouteServiceProvider (host-aware central + tenant).

    // School membership application (always available on Sahodaya tenants)
    Route::get('/school-register', [SchoolApplicationController::class, 'create'])->name('school-register.create');
    Route::post('/school-register', [SchoolApplicationController::class, 'store'])->name('school-register.store');

    // Public website (disabled until WEBSITE_ENABLED=true)
    Route::middleware('website.enabled')->group(function () {
        Route::get('/news', [NewsArticleController::class, 'index'])->name('tenant.news.index');
        Route::get('/news/{slug}', [NewsArticleController::class, 'show'])->name('tenant.news.show');
        Route::get('/events', [EventController::class, 'index'])->name('tenant.events.index');
        Route::get('/events/{slug}', [EventController::class, 'show'])->name('tenant.events.show');
        Route::get('/gallery/{slug}', [GalleryAlbumController::class, 'show'])->name('tenant.gallery.show');

        Route::post('/admission-enquiry', [AdmissionEnquiryController::class, 'store'])->name('admission-enquiry.store');
        Route::post('/tc-request', [TcRequestController::class, 'store'])->name('tc-request.store');

        Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
        Route::get('/robots.txt', [SeoController::class, 'robots']);
    });
});
