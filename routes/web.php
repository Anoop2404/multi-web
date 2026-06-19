<?php

use App\Http\Controllers\Admin\BuilderApiController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\SchoolAdmin\AnnualRegistrationController;
use App\Http\Controllers\SchoolAdmin\AlumniController;
use App\Http\Controllers\SchoolAdmin\BoardResultController;
use App\Http\Controllers\SchoolAdmin\DashboardController;
use App\Http\Controllers\SchoolAdmin\DownloadController;
use App\Http\Controllers\SchoolAdmin\EnquiryController;
use App\Http\Controllers\SchoolAdmin\EventController;
use App\Http\Controllers\SchoolAdmin\GalleryController;
use App\Http\Controllers\SchoolAdmin\JobVacancyController;
use App\Http\Controllers\SchoolAdmin\NewsController;
use App\Http\Controllers\SchoolAdmin\SchoolClassController;
use App\Http\Controllers\SchoolAdmin\RegistrationProfileController;
use App\Http\Controllers\SchoolAdmin\SchoolSetupController;
use App\Http\Controllers\SchoolAdmin\SettingsController;
use App\Http\Controllers\SchoolAdmin\StaffController;
use App\Http\Controllers\SchoolAdmin\StudentController;
use App\Http\Controllers\SchoolAdmin\TcRequestController as SchoolTcRequestController;
use App\Models\SkinPreset;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

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

    // ── Sahodaya clusters & member schools (separate lists) ───────────────────
    Route::get('/sahodayas', [TenantController::class, 'indexSahodayas'])->name('sahodayas.index');
    Route::get('/sahodayas/create', [TenantController::class, 'createSahodaya'])->name('sahodayas.create');
    Route::get('/schools', [TenantController::class, 'indexSchools'])->name('schools.index');
    Route::get('/schools/create', [TenantController::class, 'createSchool'])->name('schools.create');

    Route::resource('tenants', TenantController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ])->names([
        'index'   => 'tenants.index',
        'create'  => 'tenants.create',
        'store'   => 'tenants.store',
        'show'    => 'tenants.show',
        'edit'    => 'tenants.edit',
        'update'  => 'tenants.update',
        'destroy' => 'tenants.destroy',
    ]);
    Route::post('tenants/{tenant}/logo', [TenantController::class, 'uploadLogo'])->name('tenants.logo');
    Route::post('tenants/{tenant}/database', [TenantController::class, 'saveDatabase'])->name('tenants.database');
    Route::post('tenants/{tenant}/migrate', [TenantController::class, 'migrateDatabase'])->name('tenants.migrate');
    Route::post('tenants/{tenant}/sahodaya-admin', [TenantController::class, 'saveSahodayaAdmin'])->name('tenants.sahodaya-admin.store');
    Route::delete('tenants/{tenant}/sahodaya-admin/{user}', [TenantController::class, 'destroySahodayaAdmin'])->name('tenants.sahodaya-admin.destroy');
    Route::post('tenants/{tenant}/school-admin', [TenantController::class, 'saveSchoolAdmin'])->name('tenants.school-admin.store');
    Route::delete('tenants/{tenant}/school-admin/{user}', [TenantController::class, 'destroySchoolAdmin'])->name('tenants.school-admin.destroy');

    // ── Builder Inertia pages (superadmin only, website phase) ────────────────
    Route::middleware(['role:superadmin', 'website.enabled'])->prefix('builder')->name('builder.')->group(function () {
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
    Route::middleware(['role:superadmin', 'website.enabled'])->get('/api/section-definitions', [BuilderApiController::class, 'sectionDefinitions'])->name('api.section-definitions');

    Route::middleware(['role:superadmin', 'website.enabled', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])->prefix('api/tenants/{tenantId}')->name('api.builder.')->group(function () {
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

    // ── Global master data (superadmin) ───────────────────────────────────────
    Route::middleware('role:superadmin')->prefix('master-data')->name('master-data.')->group(function () {
        Route::get('/class-categories', [MasterDataController::class, 'classCategories'])->name('class-categories');
        Route::post('/class-categories', [MasterDataController::class, 'storeClassCategory'])->name('class-categories.store');
        Route::put('/class-categories/{classCategory}', [MasterDataController::class, 'updateClassCategory'])->name('class-categories.update');
        Route::get('/teaching-types', [MasterDataController::class, 'teachingTypes'])->name('teaching-types');
        Route::post('/teaching-types', [MasterDataController::class, 'storeTeachingType'])->name('teaching-types.store');
        Route::put('/teaching-types/{teachingType}', [MasterDataController::class, 'updateTeachingType'])->name('teaching-types.update');
    });

    // ── Skin presets management (website phase) ──────────────────────────────
    Route::middleware(['role:superadmin', 'website.enabled'])->prefix('skin-presets')->name('skin-presets.')->group(function () {
        Route::get('/', fn() => inertia('SkinPresets/Index', [
            'presets' => SkinPreset::orderBy('display_order')->get(),
        ]))->name('index');
    });
});

// ── School Admin Panel ────────────────────────────────────────────────────────
Route::prefix('school-admin/{tenantId}')
    ->name('school.')
    ->middleware(['web', 'auth', 'school.admin', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {

    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/setup/code',  [SchoolSetupController::class, 'code'])->name('setup.code');
    Route::post('/setup/code', [SchoolSetupController::class, 'saveCode'])->name('setup.code.save');

    // Students & annual registration (always available)
    Route::get('/students/setup', [SchoolClassController::class, 'index'])->name('students.setup');

    Route::get('/students',                    [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/import',             [StudentController::class, 'importForm'])->name('students.import');
    Route::get('/students/import/template',    [StudentController::class, 'importTemplate'])->name('students.import.template');
    Route::post('/students/import',            [StudentController::class, 'importStore'])->name('students.import.store');
    Route::get('/students/create',                             [StudentController::class, 'create'])->name('students.create');
    Route::post('/students',                                   [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}/edit',                     [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}',                          [StudentController::class, 'update'])->name('students.update');
    Route::get('/students/{student}/photo',                   [StudentController::class, 'showPhoto'])->name('students.photo');
    Route::post('/students/{student}/photo',                   [StudentController::class, 'updatePhoto'])->name('students.photo.upload');
    Route::delete('/students/{student}',                       [StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/registration/profile', [RegistrationProfileController::class, 'show'])->name('registration.profile');
    Route::put('/registration/profile', [RegistrationProfileController::class, 'updateProfile'])->name('registration.profile.update');
    Route::put('/registration/account', [RegistrationProfileController::class, 'updateAccount'])->name('registration.account.update');

    Route::get('/registration', [AnnualRegistrationController::class, 'index'])->name('registration.index');
    Route::post('/registration/begin', [AnnualRegistrationController::class, 'begin'])->name('registration.begin');
    Route::get('/registration/students', [AnnualRegistrationController::class, 'students'])->name('registration.students');
    Route::post('/registration/students', [AnnualRegistrationController::class, 'storeStudent'])->name('registration.students.store');
    Route::get('/registration/students/{student}/image', [AnnualRegistrationController::class, 'showSubmissionStudentImage'])->name('registration.students.image');
    Route::delete('/registration/students/{student}', [AnnualRegistrationController::class, 'destroyStudent'])->name('registration.students.destroy');
    Route::get('/registration/counts', [AnnualRegistrationController::class, 'counts'])->name('registration.counts');
    Route::post('/registration/counts', [AnnualRegistrationController::class, 'saveCounts'])->name('registration.counts.save');
    Route::get('/registration/teachers', [AnnualRegistrationController::class, 'teachers'])->name('registration.teachers');
    Route::post('/registration/teachers', [AnnualRegistrationController::class, 'storeTeacher'])->name('registration.teachers.store');
    Route::delete('/registration/teachers/{teacher}', [AnnualRegistrationController::class, 'destroyTeacher'])->name('registration.teachers.destroy');
    Route::post('/registration/submit-track', [AnnualRegistrationController::class, 'submitTrack'])->name('registration.submit-track');
    Route::get('/registration/payment', [AnnualRegistrationController::class, 'payment'])->name('registration.payment');
    Route::post('/registration/payment', [AnnualRegistrationController::class, 'uploadPayment'])->name('registration.payment.upload');
    Route::get('/registration/payments/{payment}/proof', [AnnualRegistrationController::class, 'paymentProof'])->name('registration.payment.proof');

    // Website & CMS (disabled until WEBSITE_ENABLED=true)
    Route::middleware('website.enabled')->group(function () {
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
    }); // website.enabled
});

// ── Sahodaya Admin Panel ─────────────────────────────────────────────────────
Route::prefix('sahodaya-admin/{tenantId}')
    ->name('sahodaya.')
    ->middleware(['web', 'auth', 'sahodaya.admin', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {

        // Dashboard
        Route::get('/', [\App\Http\Controllers\SahodayaAdmin\DashboardController::class, 'index'])->name('dashboard');

        // Website & CMS (disabled until WEBSITE_ENABLED=true)
        Route::middleware('website.enabled')->group(function () {
        Route::get('/site-builder', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderController::class, 'index'])->name('site-builder');
        Route::get('/site-builder/section-types', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderController::class, 'sectionTypes'])->name('site-builder.section-types');

        Route::get('/office-bearers',              [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'index'])->name('office-bearers.index');
        Route::post('/office-bearers',             [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'store'])->name('office-bearers.store');
        Route::put('/office-bearers/{bearer}',     [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'update'])->name('office-bearers.update');
        Route::delete('/office-bearers/{bearer}',  [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'destroy'])->name('office-bearers.destroy');

        Route::get('/circulars',              [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'index'])->name('circulars.index');
        Route::post('/circulars',             [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'store'])->name('circulars.store');
        Route::delete('/circulars/{circular}',[\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'destroy'])->name('circulars.destroy');

        Route::get('/kalotsav',          [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'index'])->name('kalotsav.index');
        Route::post('/kalotsav',         [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'store'])->name('kalotsav.store');
        Route::get('/kalotsav/{event}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'show'])->name('kalotsav.show');
        Route::put('/kalotsav/{event}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'update'])->name('kalotsav.update');
        Route::delete('/kalotsav/{event}', [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroy'])->name('kalotsav.destroy');

        Route::post('/kalotsav/{event}/categories',               [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'storeCategory'])->name('kalotsav.categories.store');
        Route::delete('/kalotsav/{event}/categories/{category}',  [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroyCategory'])->name('kalotsav.categories.destroy');

        Route::post('/kalotsav/{event}/results',          [\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'storeResult'])->name('kalotsav.results.store');
        Route::delete('/kalotsav/{event}/results/{result}',[\App\Http\Controllers\SahodayaAdmin\KalotsavController::class, 'destroyResult'])->name('kalotsav.results.destroy');
        }); // website.enabled

        // Portal & website content (portal always available; full website tabs when enabled)
        Route::get('/public-content',  [\App\Http\Controllers\SahodayaAdmin\PublicContentController::class, 'index'])->name('public-content.index');
        Route::put('/public-content',  [\App\Http\Controllers\SahodayaAdmin\PublicContentController::class, 'update'])->name('public-content.update');

        // Membership & registration (always available)
        Route::get('/schools', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'index'])->name('schools.index');
        Route::get('/schools/export', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'export'])->name('schools.export');
        Route::get('/schools/{school}/students', [\App\Http\Controllers\SahodayaAdmin\SchoolStudentsController::class, 'show'])->name('schools.students');
        Route::get('/schools/{school}', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'show'])->name('schools.show');
        Route::post('/schools/{school}/reject', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'reject'])->name('schools.reject');

        // Membership settings
        Route::get('/membership/settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'index'])->name('membership.settings');
        Route::put('/membership/settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateProfile'])->name('membership.settings.update');
        Route::put('/membership/fees', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMembershipFees'])->name('membership.fees.update');
        Route::put('/membership/payment-details', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updatePaymentDetails'])->name('membership.payment-details.update');
        Route::put('/membership/mail-settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMailSettings'])->name('membership.mail-settings.update');
        Route::post('/membership/mail-settings/test', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'testMailSettings'])->name('membership.mail-settings.test');
        Route::post('/membership/fee-slabs', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeFeeSlab'])->name('membership.fee-slabs.store');
        Route::delete('/membership/fee-slabs/{feeSlab}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyFeeSlab'])->name('membership.fee-slabs.destroy');
        Route::put('/membership/registration-window', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateRegistrationWindow'])->name('membership.window.update');
        Route::post('/membership/custom-categories', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeCustomCategory'])->name('membership.custom-categories.store');
        Route::put('/membership/custom-categories/{classCategory}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateCustomCategory'])->name('membership.custom-categories.update');
        Route::delete('/membership/custom-categories/{classCategory}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyCustomCategory'])->name('membership.custom-categories.destroy');
        Route::post('/membership/classes', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeMasterClass'])->name('membership.classes.store');
        Route::put('/membership/classes/{masterClass}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMasterClass'])->name('membership.classes.update');
        Route::delete('/membership/classes/{masterClass}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyMasterClass'])->name('membership.classes.destroy');
        Route::post('/membership/category-overrides', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'toggleCategoryOverride'])->name('membership.category-overrides.toggle');
        Route::put('/membership/global-categories/{classCategory}/sort', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateGlobalCategorySort'])->name('membership.global-categories.sort');
        Route::post('/membership/custom-teaching-types', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeCustomTeachingType'])->name('membership.custom-types.store');
        Route::post('/membership/type-overrides', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'toggleTypeOverride'])->name('membership.type-overrides.toggle');

        // Submission review
        Route::get('/membership/submissions', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'index'])->name('membership.submissions.index');
        Route::get('/membership/submissions/{submission}', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'show'])->name('membership.submissions.show');
        Route::get('/membership/submission-students/{student}/image', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'showSubmissionStudentImage'])->name('membership.submission-students.image');

        // Payment verification
        Route::get('/membership/payments', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'index'])->name('membership.payments.index');
        Route::get('/membership/payments/export', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'export'])->name('membership.payments.export');
        Route::get('/membership/payments/{payment}/proof', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'proof'])->name('membership.payments.proof');
        Route::post('/membership/payments/{payment}/verify', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'verify'])->name('membership.payments.verify');

        Route::get('/membership/reports', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'index'])->name('membership.reports');
        Route::get('/membership/reports/export/schools', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportSchools'])->name('membership.reports.export.schools');
        Route::get('/membership/reports/export/payments-pending', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPaymentsPending'])->name('membership.reports.export.payments-pending');
        Route::get('/membership/reports/export/payments-done', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPaymentsDone'])->name('membership.reports.export.payments-done');
        Route::get('/membership/reports/export/submissions', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportSubmissions'])->name('membership.reports.export.submissions');
        Route::get('/membership/reports/export/payments', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPayments'])->name('membership.reports.export.payments');

        Route::post('/membership/logo', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'uploadLogo'])->name('membership.logo');
        Route::put('/membership/application-form', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateApplicationForm'])->name('membership.application-form.update');
    });

// Auth routes
Route::middleware('web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', [AuthController::class, 'verifyNotice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->name('verification.send');
    });
});
