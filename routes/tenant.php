<?php

declare(strict_types=1);

use App\Http\Controllers\Public\PublicSiteController;
use App\Http\Controllers\Public\AdmissionEnquiryController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Controllers\Public\TcRequestController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', [PublicSiteController::class, 'home']);

    // Public form submissions
    Route::post('/admission-enquiry', [AdmissionEnquiryController::class, 'store'])->name('admission-enquiry.store');
    Route::post('/tc-request', [TcRequestController::class, 'store'])->name('tc-request.store');

    // SEO
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
    Route::get('/robots.txt',  [SeoController::class, 'robots']);
});
