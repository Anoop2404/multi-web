<?php

declare(strict_types=1);

use App\Http\Controllers\Public\PublicSiteController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Public-facing tenant routes (school/sahodaya websites)
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', [PublicSiteController::class, 'home']);
    // Additional public pages added here as features are built
});
