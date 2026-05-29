<?php

use App\Http\Controllers\Admin\BuilderApiController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\SchoolAdmin\AchievementController;
use App\Http\Controllers\SchoolAdmin\AlumniController;
use App\Http\Controllers\SchoolAdmin\BoardResultController;
use App\Http\Controllers\SchoolAdmin\DashboardController;
use App\Http\Controllers\SchoolAdmin\DownloadController;
use App\Http\Controllers\SchoolAdmin\EnquiryController;
use App\Http\Controllers\SchoolAdmin\EventController;
use App\Http\Controllers\SchoolAdmin\GalleryController;
use App\Http\Controllers\SchoolAdmin\JobVacancyController;
use App\Http\Controllers\SchoolAdmin\NewsController;
use App\Http\Controllers\SchoolAdmin\SettingsController;
use App\Http\Controllers\SchoolAdmin\StaffController;
use App\Http\Controllers\SchoolAdmin\TcRequestController as SchoolTcRequestController;
use App\Models\SkinPreset;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', fn() => redirect()->route('admin.dashboard'));

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        $stats = [
            'total_tenants'   => \App\Models\Tenant::count(),
            'school_tenants'  => \App\Models\Tenant::where('type', 'school')->count(),
            'sahodaya_tenants'=> \App\Models\Tenant::where('type', 'sahodaya')->count(),
            'active_tenants'  => \App\Models\Tenant::where('is_active', true)->count(),
        ];
        return inertia('Dashboard', compact('stats'));
    })->name('dashboard');

    // ── Tenant CRUD ──────────────────────────────────────────────────────────
    Route::resource('tenants', TenantController::class)->names([
        'index'   => 'tenants.index',
        'create'  => 'tenants.create',
        'store'   => 'tenants.store',
        'show'    => 'tenants.show',
        'edit'    => 'tenants.edit',
        'update'  => 'tenants.update',
        'destroy' => 'tenants.destroy',
    ]);

    // ── Builder Inertia pages (superadmin only) ───────────────────────────────
    Route::middleware('role:superadmin')->prefix('builder')->name('builder.')->group(function () {
        Route::get('/sections', function () {
            return inertia('Builder/Sections', [
                'tenants' => Tenant::active()->orderBy('name')->get(['id', 'name', 'type']),
            ]);
        })->name('sections');

        Route::get('/theme', function () {
            return inertia('Builder/Theme', [
                'tenants' => Tenant::active()->orderBy('name')->get(['id', 'name', 'type']),
                'presets' => SkinPreset::where('is_active', true)->orderBy('display_order')->get(),
            ]);
        })->name('theme');

        Route::get('/nav', function () {
            return inertia('Builder/Nav', [
                'tenants' => Tenant::active()->orderBy('name')->get(['id', 'name', 'type']),
            ]);
        })->name('nav');

        Route::get('/widgets', function () {
            return inertia('Builder/Widgets', [
                'tenants' => Tenant::active()->orderBy('name')->get(['id', 'name', 'type']),
            ]);
        })->name('widgets');

        Route::get('/footer', function () {
            return inertia('Builder/Footer', [
                'tenants' => Tenant::active()->orderBy('name')->get(['id', 'name', 'type']),
            ]);
        })->name('footer');
    });

    // ── Builder REST API (JSON, no Inertia) ───────────────────────────────────
    Route::middleware('role:superadmin')->prefix('api/tenants/{tenantId}')->name('api.builder.')->group(function () {
        // Sections
        Route::get('/sections',                      [BuilderApiController::class, 'sections'])->name('sections.index');
        Route::post('/sections',                     [BuilderApiController::class, 'storeSection'])->name('sections.store');
        Route::patch('/sections/{sectionId}',        [BuilderApiController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{sectionId}',       [BuilderApiController::class, 'deleteSection'])->name('sections.delete');
        Route::post('/sections/{sectionId}/toggle',  [BuilderApiController::class, 'toggleSection'])->name('sections.toggle');
        Route::post('/sections/reorder',             [BuilderApiController::class, 'reorderSections'])->name('sections.reorder');

        // Settings
        Route::get('/settings/{key}',  [BuilderApiController::class, 'getSetting'])->name('settings.get');
        Route::post('/settings',       [BuilderApiController::class, 'saveSetting'])->name('settings.save');

        // Nav
        Route::get('/nav',             [BuilderApiController::class, 'getNav'])->name('nav.get');
        Route::post('/nav',            [BuilderApiController::class, 'saveNav'])->name('nav.save');

        // Footer
        Route::get('/footer',          [BuilderApiController::class, 'getFooter'])->name('footer.get');
        Route::post('/footer',         [BuilderApiController::class, 'saveFooter'])->name('footer.save');

        // Theme
        Route::get('/theme',           [BuilderApiController::class, 'getTheme'])->name('theme.get');
        Route::post('/theme',          [BuilderApiController::class, 'saveTheme'])->name('theme.save');

        // Widgets
        Route::get('/widgets',         [BuilderApiController::class, 'getWidgets'])->name('widgets.get');
        Route::post('/widgets',        [BuilderApiController::class, 'saveWidgets'])->name('widgets.save');
    });

    // ── Skin presets management ────────────────────────────────────────────────
    Route::middleware('role:superadmin')->prefix('skin-presets')->name('skin-presets.')->group(function () {
        Route::get('/', fn() => inertia('SkinPresets/Index', [
            'presets' => SkinPreset::orderBy('display_order')->get(),
        ]))->name('index');
    });
});

// ── School Admin Panel ────────────────────────────────────────────────────────
Route::prefix('school-admin/{tenantId}')
    ->name('school.')
    ->middleware(['web', 'auth', 'school.admin'])
    ->group(function () {

    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');

    // News
    Route::get('/news',                  [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/create',           [NewsController::class, 'create'])->name('news.create');
    Route::post('/news',                 [NewsController::class, 'store'])->name('news.store');
    Route::get('/news/{news}/edit',      [NewsController::class, 'edit'])->name('news.edit');
    Route::put('/news/{news}',           [NewsController::class, 'update'])->name('news.update');
    Route::delete('/news/{news}',        [NewsController::class, 'destroy'])->name('news.destroy');

    // Events
    Route::get('/events',                [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create',         [EventController::class, 'create'])->name('events.create');
    Route::post('/events',               [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit',   [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}',        [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}',     [EventController::class, 'destroy'])->name('events.destroy');

    // Gallery
    Route::get('/gallery',                           [GalleryController::class, 'index'])->name('gallery.index');
    Route::post('/gallery/albums',                   [GalleryController::class, 'storeAlbum'])->name('gallery.albums.store');
    Route::post('/gallery/albums/{album}/photos',    [GalleryController::class, 'uploadPhotos'])->name('gallery.photos.upload');
    Route::delete('/gallery/albums/{album}',         [GalleryController::class, 'destroyAlbum'])->name('gallery.albums.destroy');
    Route::delete('/gallery/photos/{photo}',         [GalleryController::class, 'destroyPhoto'])->name('gallery.photos.destroy');

    // Staff
    Route::get('/staff',                 [StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create',          [StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff',                [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staff}/edit',    [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{staff}',         [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}',      [StaffController::class, 'destroy'])->name('staff.destroy');

    // Achievements
    Route::get('/achievements',                       [AchievementController::class, 'index'])->name('achievements.index');
    Route::post('/achievements',                      [AchievementController::class, 'store'])->name('achievements.store');
    Route::put('/achievements/{achievement}',         [AchievementController::class, 'update'])->name('achievements.update');
    Route::delete('/achievements/{achievement}',      [AchievementController::class, 'destroy'])->name('achievements.destroy');

    // Downloads
    Route::get('/downloads',             [DownloadController::class, 'index'])->name('downloads.index');
    Route::post('/downloads',            [DownloadController::class, 'store'])->name('downloads.store');
    Route::delete('/downloads/{download}', [DownloadController::class, 'destroy'])->name('downloads.destroy');

    // Enquiries
    Route::get('/enquiries',                        [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::patch('/enquiries/{enquiry}',            [EnquiryController::class, 'update'])->name('enquiries.update');

    // TC Requests
    Route::get('/tc-requests',                      [SchoolTcRequestController::class, 'index'])->name('tc-requests.index');
    Route::patch('/tc-requests/{tcRequest}',        [SchoolTcRequestController::class, 'update'])->name('tc-requests.update');

    // Job Vacancies
    Route::get('/job-vacancies',                    [JobVacancyController::class, 'index'])->name('job-vacancies.index');
    Route::post('/job-vacancies',                   [JobVacancyController::class, 'store'])->name('job-vacancies.store');
    Route::put('/job-vacancies/{vacancy}',          [JobVacancyController::class, 'update'])->name('job-vacancies.update');
    Route::delete('/job-vacancies/{vacancy}',       [JobVacancyController::class, 'destroy'])->name('job-vacancies.destroy');

    // Settings
    Route::get('/settings',   [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings',  [SettingsController::class, 'update'])->name('settings.update');

    // Board Results
    Route::get('/board-results',                                   [BoardResultController::class, 'index'])->name('board-results.index');
    Route::post('/board-results',                                  [BoardResultController::class, 'store'])->name('board-results.store');
    Route::delete('/board-results/{boardResult}',                  [BoardResultController::class, 'destroy'])->name('board-results.destroy');
    Route::get('/board-results/{boardResult}/toppers',             [BoardResultController::class, 'toppers'])->name('board-results.toppers');
    Route::post('/board-results/{boardResult}/toppers',            [BoardResultController::class, 'storeTopper'])->name('board-results.toppers.store');
    Route::delete('/board-results/{boardResult}/toppers/{topper}', [BoardResultController::class, 'destroyTopper'])->name('board-results.toppers.destroy');

    // Alumni
    Route::get('/alumni',                         [AlumniController::class, 'index'])->name('alumni.index');
    Route::patch('/alumni/{alumnus}/approve',     [AlumniController::class, 'approve'])->name('alumni.approve');
    Route::patch('/alumni/{alumnus}/feature',     [AlumniController::class, 'feature'])->name('alumni.feature');
    Route::delete('/alumni/{alumnus}',            [AlumniController::class, 'destroy'])->name('alumni.destroy');

    // Testimonials
    Route::get('/testimonials',                              [\App\Http\Controllers\SchoolAdmin\TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('/testimonials',                             [\App\Http\Controllers\SchoolAdmin\TestimonialController::class, 'store'])->name('testimonials.store');
    Route::put('/testimonials/{testimonial}',                [\App\Http\Controllers\SchoolAdmin\TestimonialController::class, 'update'])->name('testimonials.update');
    Route::delete('/testimonials/{testimonial}',             [\App\Http\Controllers\SchoolAdmin\TestimonialController::class, 'destroy'])->name('testimonials.destroy');

    // Contact
    Route::get('/contact', function (\App\Models\Tenant $tenantId) {
        $settings = $tenantId->settings()->get()->pluck('value', 'key')->toArray();
        return inertia('School/Contact/Edit', [
            'school'   => $tenantId->only('id', 'name', 'type'),
            'settings' => $settings,
        ]);
    })->name('contact.index');
    Route::post('/contact',                                  [SettingsController::class, 'update'])->name('contact.update');
});

// ── Sahodaya Admin Panel ─────────────────────────────────────────────────────
Route::prefix('sahodaya-admin/{tenantId}')
    ->name('sahodaya.')
    ->middleware(['web', 'auth', 'sahodaya.admin'])
    ->group(function () {

        // Dashboard
        Route::get('/', [\App\Http\Controllers\SahodayaAdmin\DashboardController::class, 'index'])->name('dashboard');

        // Office Bearers
        Route::get('/office-bearers',              [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'index'])->name('office-bearers.index');
        Route::post('/office-bearers',             [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'store'])->name('office-bearers.store');
        Route::put('/office-bearers/{bearer}',     [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'update'])->name('office-bearers.update');
        Route::delete('/office-bearers/{bearer}',  [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'destroy'])->name('office-bearers.destroy');

        // Circulars
        Route::get('/circulars',              [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'index'])->name('circulars.index');
        Route::post('/circulars',             [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'store'])->name('circulars.store');
        Route::delete('/circulars/{circular}',[\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'destroy'])->name('circulars.destroy');

        // Kalotsav Events
        Route::get('/kalotsav',          [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'index'])->name('kalotsav.index');
        Route::post('/kalotsav',         [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'store'])->name('kalotsav.store');
        Route::get('/kalotsav/{event}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'show'])->name('kalotsav.show');
        Route::put('/kalotsav/{event}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'update'])->name('kalotsav.update');
        Route::delete('/kalotsav/{event}', [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroy'])->name('kalotsav.destroy');

        // Kalotsav Categories (nested under event)
        Route::post('/kalotsav/{event}/categories',               [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'storeCategory'])->name('kalotsav.categories.store');
        Route::delete('/kalotsav/{event}/categories/{category}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroyCategory'])->name('kalotsav.categories.destroy');

        // Kalotsav Results
        Route::post('/kalotsav/{event}/results',          [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'storeResult'])->name('kalotsav.results.store');
        Route::delete('/kalotsav/{event}/results/{result}',[\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroyResult'])->name('kalotsav.results.destroy');

        // Member Schools (read-only overview)
        Route::get('/schools', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'index'])->name('schools.index');
    });

// Auth routes
Route::middleware('web')->group(function () {
    Route::get('/login', fn() => inertia('Auth/Login'))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
