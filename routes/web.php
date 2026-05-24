<?php

use Illuminate\Support\Facades\Route;

// Central domain routes — admin panel and auth
Route::get('/', fn() => redirect()->route('admin.dashboard'));

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', fn() => inertia('Dashboard'))->name('dashboard');

    // Tenant management
    Route::resource('tenants', \App\Http\Controllers\Admin\TenantController::class);

    // Site builder (superadmin only)
    Route::prefix('builder/{tenant}')->name('builder.')->middleware('role:superadmin')->group(function () {
        Route::get('/', fn() => inertia('Builder/Index'))->name('index');
        Route::get('/sections', fn() => inertia('Builder/Sections'))->name('sections');
        Route::get('/theme', fn() => inertia('Builder/Theme'))->name('theme');
        Route::get('/nav', fn() => inertia('Builder/Nav'))->name('nav');
        Route::get('/footer', fn() => inertia('Builder/Footer'))->name('footer');
    });
});

// Auth routes (stub — swap with Laravel Breeze/Fortify if needed)
Route::middleware('web')->group(function () {
    Route::get('/login', fn() => inertia('Auth/Login'))->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');
});
