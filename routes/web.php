<?php

use App\Http\Controllers\Admin\BuilderApiController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\StateAdminDashboardController;
use App\Http\Controllers\Admin\StateFestProgramController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\SahodayaAdmin\AcademicYearController;
use App\Http\Controllers\SahodayaAdmin\FestAttendanceController;
use App\Http\Controllers\SahodayaAdmin\FestCatalogController;
use App\Http\Controllers\SahodayaAdmin\FestEventController;
use App\Http\Controllers\SahodayaAdmin\FestMarkEntryController;
use App\Http\Controllers\SahodayaAdmin\FestRegistrationReviewController;
use App\Http\Controllers\SahodayaAdmin\FestChestNumberController;
use App\Http\Controllers\SahodayaAdmin\FestCertificateController;
use App\Http\Controllers\SahodayaAdmin\FestCertificateOpsController;
use App\Http\Controllers\SahodayaAdmin\FestChampionshipController;
use App\Http\Controllers\SahodayaAdmin\FestEventSettingsController;
use App\Http\Controllers\SahodayaAdmin\FestMarksImportController;
use App\Http\Controllers\SahodayaAdmin\FestScheduleController;
use App\Http\Controllers\SahodayaAdmin\FestJudgeAssignmentController;
use App\Http\Controllers\SahodayaAdmin\FestExportController;
use App\Http\Controllers\SahodayaAdmin\FestEventFeesController;
use App\Http\Controllers\SahodayaAdmin\SportsAgeGroupController;
use App\Http\Controllers\SahodayaAdmin\LedgerController;
use App\Http\Controllers\SahodayaAdmin\McqExamController;
use App\Http\Controllers\SahodayaAdmin\TrainingProgramController;
use App\Http\Controllers\SahodayaAdmin\TrainingResourcePersonController;
use App\Http\Controllers\SahodayaAdmin\FestResultsController;
use App\Http\Controllers\SahodayaAdmin\ScreenSettingController;
use App\Http\Controllers\Portal\JudgeDashboardController;
use App\Http\Controllers\Portal\StudentDashboardController;
use App\Http\Controllers\Portal\TeacherDashboardController;
use App\Http\Controllers\PublicCertificateController;
use App\Http\Controllers\DisplayScreenController;
use App\Http\Controllers\SahodayaAdmin\FestHouseController;
use App\Http\Controllers\SahodayaAdmin\FestAppealController;
use App\Http\Controllers\SahodayaAdmin\FestCateringController;
use App\Http\Controllers\SchoolAdmin\FestRegistrationController;
use App\Http\Controllers\SchoolAdmin\FestProgramController;
use App\Http\Controllers\SchoolAdmin\FestEventPortalController;
use App\Http\Controllers\SchoolAdmin\SportsMeetController;
use App\Http\Controllers\SchoolAdmin\TeacherController;
use App\Http\Controllers\SchoolAdmin\CircularAcknowledgementController;
use App\Http\Controllers\SchoolAdmin\AchievementController;
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
use App\Http\Controllers\SchoolAdmin\SiteBuilderController;
use App\Http\Controllers\SchoolAdmin\SiteBuilderApiController;
use App\Http\Controllers\SchoolAdmin\StudentController;
use App\Http\Controllers\SchoolAdmin\StudentSportsController;
use App\Models\SkinPreset;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', 'password.change'])->group(function () {

    // ── State-level routes (state admin + superadmin bypass) ────────────────
    Route::middleware('state.admin')->group(function () {
        Route::get('/state-dashboard', [StateAdminDashboardController::class, 'index'])->name('state.dashboard');

        Route::prefix('state-remittances')->name('state-remittances.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\StateRemittanceController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\StateRemittanceController::class, 'store'])->name('store');
            Route::post('/{remittance}/verify', [\App\Http\Controllers\Admin\StateRemittanceController::class, 'verify'])->name('verify');
            Route::post('/{remittance}/reject', [\App\Http\Controllers\Admin\StateRemittanceController::class, 'reject'])->name('reject');
            Route::get('/{remittance}/proof', [\App\Http\Controllers\Admin\StateRemittanceController::class, 'proof'])->name('proof');
        });

        Route::prefix('state-programs')->name('state-programs.')->group(function () {
            Route::get('/', [StateFestProgramController::class, 'index'])->name('index');
            Route::post('/', [StateFestProgramController::class, 'store'])->name('store');
            Route::get('/{stateProgram}', [StateFestProgramController::class, 'show'])->name('show');
            Route::put('/{stateProgram}', [StateFestProgramController::class, 'update'])->name('update');
            Route::delete('/{stateProgram}', [StateFestProgramController::class, 'destroy'])->name('destroy');
            Route::post('/{stateProgram}/publish', [StateFestProgramController::class, 'publish'])->name('publish');
            Route::post('/{stateProgram}/items', [StateFestProgramController::class, 'storeItem'])->name('items.store');
            Route::delete('/{stateProgram}/items/{item}', [StateFestProgramController::class, 'destroyItem'])->name('items.destroy');
        });

        Route::prefix('kalotsav')->name('kalotsav.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\KalotsavStateController::class, 'index'])->name('index');
            Route::get('/{stateProgram}', [\App\Http\Controllers\Admin\KalotsavStateController::class, 'show'])->name('show');
            Route::get('/{stateProgram}/results', [\App\Http\Controllers\Admin\KalotsavStateController::class, 'results'])->name('results');
            Route::get('/{stateProgram}/winners', [\App\Http\Controllers\Admin\KalotsavStateController::class, 'winners'])->name('winners');
            Route::get('/{stateProgram}/winners/export', [\App\Http\Controllers\Admin\KalotsavStateController::class, 'exportWinners'])->name('winners.export');
        });

        Route::prefix('state-workspace')->name('state.')->group(function () {
            Route::get('/qualifiers', [\App\Http\Controllers\StateAdmin\StateQualifierReviewController::class, 'index'])->name('qualifiers.index');
            Route::get('/qualifiers/{intake}', [\App\Http\Controllers\StateAdmin\StateQualifierReviewController::class, 'show'])->name('qualifiers.show');
            Route::post('/qualifiers/{intake}/approve', [\App\Http\Controllers\StateAdmin\StateQualifierReviewController::class, 'approve'])->name('qualifiers.approve');
            Route::get('/fest', [\App\Http\Controllers\StateAdmin\StateFestWorkspaceController::class, 'index'])->name('fest.index');
            Route::post('/fest', [\App\Http\Controllers\StateAdmin\StateFestWorkspaceController::class, 'store'])->name('fest.store');
            Route::get('/fest/{event}', [\App\Http\Controllers\StateAdmin\StateFestWorkspaceController::class, 'show'])->name('fest.show');
        });

        Route::get('/sports', [\App\Http\Controllers\Admin\SportsResultsController::class, 'index'])->name('sports.index');
        Route::get('/board-results', [\App\Http\Controllers\StateAdmin\StateBoardResultsController::class, 'index'])->name('board-results.index');

        Route::get('/sahodayas', [TenantController::class, 'indexSahodayas'])->name('sahodayas.index');
    });

    // ── Superadmin-only platform routes ─────────────────────────────────────
    Route::middleware('super.admin')->group(function () {

    Route::get('/dashboard', function () {
        $stats = [
            'total_tenants'   => \App\Models\Tenant::count(),
            'school_tenants'  => \App\Models\Tenant::where('type', 'school')->count(),
            'sahodaya_tenants'=> \App\Models\Tenant::where('type', 'sahodaya')->count(),
            'active_tenants'  => \App\Models\Tenant::where('is_active', true)->count(),
        ];
        return inertia('Dashboard', compact('stats'));
    })->name('dashboard');

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
    Route::post('tenants/{tenant}/reject-membership', [TenantController::class, 'rejectMembership'])->name('tenants.reject-membership');
    Route::delete('tenants/{tenant}/erase-students', [TenantController::class, 'eraseStudents'])->name('tenants.erase-students');
    Route::post('tenants/{tenant}/erasure-batches/{batchId}/restore', [TenantController::class, 'restoreErasedStudents'])->name('tenants.erasure-batches.restore');
    Route::put('tenants/{tenant}/nav-visibility', [TenantController::class, 'updateNavVisibility'])->name('tenants.nav-visibility.update');

    Route::get('/storage-migration', [\App\Http\Controllers\Admin\StorageMigrationController::class, 'index'])->name('storage-migration');
    Route::get('/storage-migration/scan', [\App\Http\Controllers\Admin\StorageMigrationController::class, 'scan'])->name('storage-migration.scan');
    Route::post('/storage-migration/migrate', [\App\Http\Controllers\Admin\StorageMigrationController::class, 'migrate'])->name('storage-migration.migrate');
    Route::get('/storage-migration/progress', [\App\Http\Controllers\Admin\StorageMigrationController::class, 'progress'])->name('storage-migration.progress');

    // ── Builder Inertia pages (superadmin only, website phase) ────────────────
    Route::middleware(['website.enabled'])->prefix('builder')->name('builder.')->group(function () {
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
    Route::middleware(['website.enabled'])->get('/api/section-definitions', [BuilderApiController::class, 'sectionDefinitions'])->name('api.section-definitions');

    Route::middleware(['website.enabled', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])->prefix('api/tenants/{tenantId}')->name('api.builder.')->group(function () {
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
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::get('/class-categories', [MasterDataController::class, 'classCategories'])->name('class-categories');
        Route::post('/class-categories', [MasterDataController::class, 'storeClassCategory'])->name('class-categories.store');
        Route::put('/class-categories/{classCategory}', [MasterDataController::class, 'updateClassCategory'])->name('class-categories.update');
        Route::get('/teaching-types', [MasterDataController::class, 'teachingTypes'])->name('teaching-types');
        Route::post('/teaching-types', [MasterDataController::class, 'storeTeachingType'])->name('teaching-types.store');
        Route::put('/teaching-types/{teachingType}', [MasterDataController::class, 'updateTeachingType'])->name('teaching-types.update');
        Route::get('/subjects', [MasterDataController::class, 'subjects'])->name('subjects');
        Route::post('/subjects', [MasterDataController::class, 'storeSubject'])->name('subjects.store');
        Route::put('/subjects/{subject}', [MasterDataController::class, 'updateSubject'])->name('subjects.update');
        Route::get('/designations', [MasterDataController::class, 'designations'])->name('designations');
        Route::post('/designations', [MasterDataController::class, 'storeDesignation'])->name('designations.store');
        Route::put('/designations/{designation}', [MasterDataController::class, 'updateDesignation'])->name('designations.update');
        Route::get('/age-categories', [MasterDataController::class, 'ageCategories'])->name('age-categories');
        Route::post('/age-categories', [MasterDataController::class, 'storeAgeCategory'])->name('age-categories.store');
        Route::put('/age-categories/{ageCategory}', [MasterDataController::class, 'updateAgeCategory'])->name('age-categories.update');
    });

    // ── Skin presets management (website phase) ──────────────────────────────
  Route::middleware(['website.enabled'])->prefix('skin-presets')->name('skin-presets.')->group(function () {
        Route::get('/', fn() => inertia('SkinPresets/Index', [
            'presets' => SkinPreset::orderBy('display_order')->get(),
        ]))->name('index');
    });

    // ── Subscription & Billing (Phase 8) ─────────────────────────────────────
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/',                                      [SubscriptionController::class, 'index'])->name('index');
        Route::post('/plans',                                [SubscriptionController::class, 'storePlan'])->name('plans.store');
        Route::post('/subscriptions',                        [SubscriptionController::class, 'storeTenantSubscription'])->name('subscriptions.store');
        Route::post('/invoices',                             [SubscriptionController::class, 'storeInvoice'])->name('invoices.store');
        Route::post('/receipts/{receipt}/approve',           [SubscriptionController::class, 'approveReceipt'])->name('receipts.approve');
        Route::post('/receipts/{receipt}/reject',            [SubscriptionController::class, 'rejectReceipt'])->name('receipts.reject');
        Route::get('/receipts/{receipt}/file',               [SubscriptionController::class, 'showReceiptFile'])->name('receipts.file');
    });

    Route::redirect('audit', 'audit-logs');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    Route::prefix('state-users')->name('state-users.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\StateUserController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\StateUserController::class, 'store'])->name('store');
        Route::put('/{user}', [\App\Http\Controllers\Admin\StateUserController::class, 'update'])->name('update');
        Route::delete('/{user}', [\App\Http\Controllers\Admin\StateUserController::class, 'destroy'])->name('destroy');
    });

    }); // end super.admin
});

// ── School Admin Panel ────────────────────────────────────────────────────────
Route::prefix('school-admin/{tenantId}')
    ->name('school.')
    ->middleware(['web', 'auth', 'password.change', 'school.admin', 'event.coordinator', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {

    Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/setup/dismiss-wizard', [DashboardController::class, 'dismissSetupWizard'])->name('setup.dismiss-wizard');

    Route::get('/setup/code',  [SchoolSetupController::class, 'code'])->name('setup.code');
    Route::post('/setup/code', [SchoolSetupController::class, 'saveCode'])->name('setup.code.save');

    // Students & annual registration (always available)
        Route::get('/calendar', [\App\Http\Controllers\SchoolAdmin\CalendarController::class, 'index'])->name('calendar');
        Route::get('/calendar/export.ics', [\App\Http\Controllers\SchoolAdmin\CalendarController::class, 'exportIcal'])->name('calendar.ical');

    Route::get('/documents', [\App\Http\Controllers\SchoolAdmin\SchoolDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [\App\Http\Controllers\SchoolAdmin\SchoolDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [\App\Http\Controllers\SchoolAdmin\SchoolDocumentController::class, 'download'])->name('documents.download');

    Route::get('/students/setup', [SchoolClassController::class, 'index'])->name('students.setup');

    Route::get('/imports', [\App\Http\Controllers\SchoolAdmin\ImportHistoryController::class, 'index'])->name('imports.index');
    Route::get('/imports/{backup}/preview', [\App\Http\Controllers\SchoolAdmin\ImportHistoryController::class, 'preview'])->name('imports.preview');
    Route::get('/imports/{backup}/download', [\App\Http\Controllers\SchoolAdmin\ImportHistoryController::class, 'download'])->name('imports.download');

    Route::get('/students',                    [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/export',             [StudentController::class, 'export'])->name('students.export');
    Route::get('/students/export-pdf',         [StudentController::class, 'exportPdf'])->name('students.export.pdf');
    Route::get('/students/import',             [StudentController::class, 'importForm'])->name('students.import');
    Route::get('/students/import/template',    [StudentController::class, 'importTemplate'])->name('students.import.template');
    Route::post('/students/import/preview',    [StudentController::class, 'importPreview'])->name('students.import.preview');
    Route::post('/students/import',            [StudentController::class, 'importStore'])->name('students.import.store');
    Route::get('/students/create',                             [StudentController::class, 'create'])->name('students.create');
    Route::get('/students/bulk',                               [StudentController::class, 'createBulk'])->name('students.create.bulk');
    Route::post('/students',                                   [StudentController::class, 'store'])->name('students.store');
    Route::post('/students/bulk',                              [StudentController::class, 'storeBulk'])->name('students.store.bulk');
    Route::post('/students/backfill-reg-numbers',             [StudentController::class, 'backfillRegNumbers'])->name('students.backfill-reg-numbers');
    Route::post('/students/change-request', [StudentController::class, 'submitCreateChangeRequest'])->name('students.change-request.create');
    Route::post('/students/{student}/change-request', [StudentController::class, 'submitChangeRequest'])->name('students.change-request');
    Route::get('/students/change-requests', [StudentController::class, 'changeRequests'])->name('students.change-requests');
    Route::get('/students/pending-change-requests', [\App\Http\Controllers\SchoolAdmin\StudentChangeRequestController::class, 'index'])->name('students.pending-change-requests');
    Route::post('/students/pending-change-requests/{changeRequest}/approve', [\App\Http\Controllers\SchoolAdmin\StudentChangeRequestController::class, 'approve'])->name('students.pending-change-requests.approve');
    Route::post('/students/pending-change-requests/{changeRequest}/reject', [\App\Http\Controllers\SchoolAdmin\StudentChangeRequestController::class, 'reject'])->name('students.pending-change-requests.reject');
    Route::get('/students/{student}',                          [StudentController::class, 'show'])->name('students.show');
    Route::post('/students/{student}/sports/events/{event}/register', [StudentSportsController::class, 'registerSportsEvent'])->name('students.sports.register-event');
    Route::post('/students/{student}/sports/events/{event}/items', [StudentSportsController::class, 'registerSportsItems'])->name('students.sports.register-items');
    Route::get('/students/{student}/sports/events/{event}/eligible-items', [StudentSportsController::class, 'eligibleSportsItems'])->name('students.sports.eligible-items');
    Route::get('/students/{student}/fest/{event}/id-card', [StudentSportsController::class, 'festIdCard'])->name('students.fest.id-card');
    Route::get('/students/{student}/edit',                     [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}',                          [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}',                      [StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/students/{student}/photo',                   [StudentController::class, 'showPhoto'])->name('students.photo');
    Route::post('/students/{student}/photo',                   [StudentController::class, 'updatePhoto'])->name('students.photo.upload');
    Route::post('/students/photos-zip',                        [StudentController::class, 'uploadPhotosZip'])->name('students.photos-zip');
    Route::get('/students/photo-naming-list',                  [StudentController::class, 'photoNamingList'])->name('students.photo-naming-list');
    Route::post('/students/{student}/portal-login', [StudentController::class, 'provisionPortal'])->name('students.portal-login');
    Route::post('/students/{student}/reset-portal-password', [StudentController::class, 'resetPortalPassword'])->name('students.reset-portal-password');

    Route::get('/users/profile-change-requests', [\App\Http\Controllers\SchoolAdmin\UserProfileChangeRequestController::class, 'index'])->name('users.profile-change-requests');
    Route::post('/users/profile-change-requests/{changeRequest}/approve', [\App\Http\Controllers\SchoolAdmin\UserProfileChangeRequestController::class, 'approve'])->name('users.profile-change-requests.approve');
    Route::post('/users/profile-change-requests/{changeRequest}/reject', [\App\Http\Controllers\SchoolAdmin\UserProfileChangeRequestController::class, 'reject'])->name('users.profile-change-requests.reject');

    Route::get('/registration/profile', [RegistrationProfileController::class, 'show'])->name('registration.profile');
    Route::put('/registration/profile', [RegistrationProfileController::class, 'updateProfile'])->name('registration.profile.update');
    Route::put('/registration/account', [RegistrationProfileController::class, 'updateAccount'])->name('registration.account.update');

    Route::get('/registration', [AnnualRegistrationController::class, 'index'])->name('registration.index');
    Route::post('/registration/begin', [AnnualRegistrationController::class, 'begin'])->name('registration.begin');
    Route::post('/registration/region', [AnnualRegistrationController::class, 'saveRegion'])->name('registration.region.save');
    Route::get('/registration/students', [AnnualRegistrationController::class, 'students'])->name('registration.students');
    Route::post('/registration/students', [AnnualRegistrationController::class, 'storeStudent'])->name('registration.students.store');
    Route::get('/registration/students/{student}/image', [AnnualRegistrationController::class, 'showSubmissionStudentImage'])->name('registration.students.image');
    Route::delete('/registration/students/{student}', [AnnualRegistrationController::class, 'destroyStudent'])->name('registration.students.destroy');
    Route::get('/registration/counts', [AnnualRegistrationController::class, 'counts'])->name('registration.counts');
    Route::post('/registration/counts', [AnnualRegistrationController::class, 'saveCounts'])->name('registration.counts.save');
    Route::get('/registration/teachers', [AnnualRegistrationController::class, 'teachers'])->name('registration.teachers');
    Route::post('/registration/teachers', [AnnualRegistrationController::class, 'storeTeacher'])->name('registration.teachers.store');
    Route::post('/registration/teachers/bulk', [AnnualRegistrationController::class, 'bulkStoreTeachers'])->name('registration.teachers.bulk');
    Route::delete('/registration/teachers/{teacher}', [AnnualRegistrationController::class, 'destroyTeacher'])->name('registration.teachers.destroy');
    Route::post('/registration/submit-track', [AnnualRegistrationController::class, 'submitTrack'])->name('registration.submit-track');
    Route::get('/registration/payment', [AnnualRegistrationController::class, 'payment'])->name('registration.payment');
    Route::post('/registration/payment', [AnnualRegistrationController::class, 'uploadPayment'])->name('registration.payment.upload');
    Route::get('/registration/payments/{payment}/proof', [AnnualRegistrationController::class, 'paymentProof'])->name('registration.payment.proof');
    Route::get('/registration/receipt/{payment}', [\App\Http\Controllers\SchoolAdmin\PaymentHistoryController::class, 'membershipReceipt'])->name('registration.receipt');
    Route::get('/membership/payment', [AnnualRegistrationController::class, 'payment'])->name('membership.payment.redirect');
    Route::get('/membership-payments', [AnnualRegistrationController::class, 'payment'])->name('membership-payments.redirect');

    Route::get('/fest/reports', [\App\Http\Controllers\SchoolAdmin\FestSchoolReportController::class, 'reportsHub'])->name('fest.reports.hub');

    require __DIR__.'/includes/school_event_programs.php';

    // Recover from malformed links that used the school UUID as the program slug.
    Route::get('/programs/{badProgram}/{rest}', function (string $tenantId, string $rest) {
        return redirect("/school-admin/{$tenantId}")
            ->with('error', 'That program link was invalid. Use Sports Meet, Kalotsav, or another program from the sidebar.');
    })
        ->where('badProgram', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
        ->name('programs.bad-slug-recover');

    Route::get('/fest/hub', [FestEventPortalController::class, 'festHub'])->name('fest.hub');
    Route::get('/fest-programs', [FestProgramController::class, 'index'])->name('fest-programs.index');
    Route::post('/fest-programs', [FestProgramController::class, 'store'])->name('fest-programs.store');
    Route::get('/fest-programs/{festProgram}', [FestProgramController::class, 'show'])->name('fest-programs.show');
    Route::post('/fest-programs/{festProgram}/link-parent', [FestProgramController::class, 'linkParent'])->name('fest-programs.link-parent');
    Route::post('/fest-programs/{festProgram}/participation-policy', [FestProgramController::class, 'storePolicy'])->name('fest-programs.participation-policy.store');
    Route::post('/fest-programs/{festProgram}/items', [FestProgramController::class, 'storeItem'])->name('fest-programs.items.store');
    Route::delete('/fest-programs/{festProgram}/items/{item}', [FestProgramController::class, 'destroyItem'])->name('fest-programs.items.destroy');
    Route::get('/fest-programs/{festProgram}/marks', [FestProgramController::class, 'marks'])->name('fest-programs.marks');
    Route::post('/fest-programs/{festProgram}/marks', [FestProgramController::class, 'storeMark'])->name('fest-programs.marks.store');
    Route::get('/fest/{event}/house', [FestEventPortalController::class, 'house'])->name('fest.house');
    Route::get('/fest/{event}/catering', [FestEventPortalController::class, 'catering'])->name('fest.catering');
    Route::post('/fest/{event}/catering', [FestEventPortalController::class, 'storeCatering'])->name('fest.catering.store');
    Route::get('/fest/{event}/appeals', [FestEventPortalController::class, 'appeals'])->name('fest.appeals.index');
    Route::post('/fest/{event}/appeals', [FestEventPortalController::class, 'storeAppeal'])->name('fest.appeals.store');
    Route::get('/fest/{event}/certificates/download-all', [FestEventPortalController::class, 'downloadCertificatesZip'])->name('fest.certificates.download-all');
    Route::get('/food-coupons', [\App\Http\Controllers\SchoolAdmin\FestFoodCouponController::class, 'index'])->name('food-coupons.index');
    Route::get('/fest/{event}/food-coupons/print', [\App\Http\Controllers\SchoolAdmin\FestFoodCouponController::class, 'print'])->name('food-coupons.print');

    Route::get('/houses', [\App\Http\Controllers\SchoolAdmin\SchoolHouseController::class, 'index'])->name('houses.index');
    Route::post('/houses', [\App\Http\Controllers\SchoolAdmin\SchoolHouseController::class, 'store'])->name('houses.store');
    Route::put('/houses/{house}', [\App\Http\Controllers\SchoolAdmin\SchoolHouseController::class, 'update'])->name('houses.update');
    Route::delete('/houses/{house}', [\App\Http\Controllers\SchoolAdmin\SchoolHouseController::class, 'destroy'])->name('houses.destroy');
    Route::post('/houses/assign-students', [\App\Http\Controllers\SchoolAdmin\SchoolHouseController::class, 'assignStudents'])->name('houses.assign-students');

    Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::get('/teachers/export', [TeacherController::class, 'export'])->name('teachers.export');
    Route::get('/teachers/export-pdf', [TeacherController::class, 'exportPdf'])->name('teachers.export.pdf');
    Route::post('/teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::post('/teachers/bulk', [TeacherController::class, 'storeBulk'])->name('teachers.store.bulk');
    Route::get('/teachers/import/template', [TeacherController::class, 'importTemplate'])->name('teachers.import.template');
    Route::post('/teachers/import', [TeacherController::class, 'importStore'])->name('teachers.import.store');
    Route::put('/teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
    Route::post('/teachers/{teacher}/provision-portal', [TeacherController::class, 'provisionPortal'])->name('teachers.provision-portal');
    Route::post('/teachers/{teacher}/reset-portal-password', [TeacherController::class, 'resetPortalPassword'])->name('teachers.reset-portal-password');
    Route::get('/teachers/{teacher}/photo', [TeacherController::class, 'showPhoto'])->name('teachers.photo');
    Route::post('/teachers/{teacher}/photo', [TeacherController::class, 'updatePhoto'])->name('teachers.photo.upload');
    Route::post('/teachers/photos-zip', [TeacherController::class, 'uploadPhotosZip'])->name('teachers.photos-zip');
    Route::delete('/teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');

    Route::get('/users', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'index'])->name('users.index');
    Route::put('/users/coordinator-contact', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'updateCoordinatorContact'])->name('users.coordinator-contact.update');
    Route::post('/users/coordinator-login', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'provisionCoordinatorFromContact'])->name('users.coordinator-login');
    Route::put('/users/leadership-contact/{roleKey}', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'updateLeadershipContact'])->name('users.leadership-contact.update');
    Route::post('/users/leadership-login/{roleKey}', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'provisionLeadershipLogin'])->name('users.leadership-login');
    Route::post('/users', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/reset-password', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('/users/{user}', [\App\Http\Controllers\SchoolAdmin\TenantUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/circulars', [CircularAcknowledgementController::class, 'index'])->name('circulars.index');
    Route::post('/circulars/{circular}/acknowledge', [CircularAcknowledgementController::class, 'acknowledge'])->name('circulars.acknowledge');
    Route::get('/circulars/{circular}/download', [CircularAcknowledgementController::class, 'download'])->name('circulars.download');

    Route::get('/notifications', [\App\Http\Controllers\SchoolAdmin\NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-read', [\App\Http\Controllers\SchoolAdmin\NotificationsController::class, 'markRead'])->name('notifications.mark-read');

    Route::get('/payments', [\App\Http\Controllers\SchoolAdmin\PaymentHistoryController::class, 'index'])->name('payments.index');
    Route::get('/payments/export', [\App\Http\Controllers\SchoolAdmin\PaymentHistoryController::class, 'export'])->name('payments.export');
    Route::get('/payments/membership/{payment}/receipt', [\App\Http\Controllers\SchoolAdmin\PaymentHistoryController::class, 'membershipReceipt'])->name('payments.membership.receipt');
    Route::get('/payments/receipts/{feeReceipt}', [\App\Http\Controllers\SchoolAdmin\PaymentHistoryController::class, 'programReceipt'])->name('payments.program.receipt');

    // Website & CMS (disabled until WEBSITE_ENABLED=true)
    Route::middleware('website.enabled')->group(function () {
    Route::get('/settings',   [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings',  [SettingsController::class, 'update'])->name('settings.update');

    Route::middleware('public.website.admin.cms')->group(function () {
    Route::get('/site-builder', [SiteBuilderController::class, 'index'])->name('site-builder');
    Route::get('/site-builder/section-types', [SiteBuilderController::class, 'sectionTypes'])->name('site-builder.section-types');

    Route::prefix('site-builder/api')->name('site-builder.api.')->group(function () {
        Route::get('/sections', [SiteBuilderApiController::class, 'sections'])->name('sections.index');
        Route::post('/sections', [SiteBuilderApiController::class, 'storeSection'])->name('sections.store');
        Route::patch('/sections/{sectionId}', [SiteBuilderApiController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{sectionId}', [SiteBuilderApiController::class, 'deleteSection'])->name('sections.delete');
        Route::post('/sections/{sectionId}/toggle', [SiteBuilderApiController::class, 'toggleSection'])->name('sections.toggle');
        Route::post('/sections/reorder', [SiteBuilderApiController::class, 'reorderSections'])->name('sections.reorder');
        Route::post('/sections/{sectionId}/publish', [SiteBuilderApiController::class, 'publishSection'])->name('sections.publish');
        Route::get('/sections/{sectionId}/versions', [SiteBuilderApiController::class, 'sectionVersions'])->name('sections.versions');
        Route::post('/sections/{sectionId}/versions/{versionId}/restore', [SiteBuilderApiController::class, 'restoreSectionVersion'])->name('sections.versions.restore');
        Route::get('/nav', [SiteBuilderApiController::class, 'getNav'])->name('nav.get');
        Route::post('/nav', [SiteBuilderApiController::class, 'saveNav'])->name('nav.save');
        Route::get('/footer', [SiteBuilderApiController::class, 'getFooter'])->name('footer.get');
        Route::post('/footer', [SiteBuilderApiController::class, 'saveFooter'])->name('footer.save');
        Route::post('/portal-links', [SiteBuilderApiController::class, 'ensurePortalLinks'])->name('portal-links.ensure');
        Route::post('/default-nav', [SiteBuilderApiController::class, 'ensureDefaultNav'])->name('default-nav.ensure');
        Route::get('/public-website', [SiteBuilderApiController::class, 'getPublicWebsite'])->name('public-website.get');
        Route::post('/public-website', [SiteBuilderApiController::class, 'savePublicWebsite'])->name('public-website.save');
    });

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

    // Job Vacancies
    Route::get('/job-vacancies',                    [JobVacancyController::class, 'index'])->name('job-vacancies.index');
    Route::post('/job-vacancies',                   [JobVacancyController::class, 'store'])->name('job-vacancies.store');
    Route::put('/job-vacancies/{vacancy}',          [JobVacancyController::class, 'update'])->name('job-vacancies.update');
    Route::delete('/job-vacancies/{vacancy}',       [JobVacancyController::class, 'destroy'])->name('job-vacancies.destroy');

    // Board Results
    Route::get('/board-results',                                   [BoardResultController::class, 'index'])->name('board-results.index');
    Route::post('/board-results',                                  [BoardResultController::class, 'store'])->name('board-results.store');
    Route::put('/board-results/{boardResult}',                     [BoardResultController::class, 'update'])->name('board-results.update');
    Route::post('/board-results/{boardResult}/submit',             [BoardResultController::class, 'submit'])->name('board-results.submit');
    Route::post('/board-results/{boardResult}/upload-pdf',         [BoardResultController::class, 'uploadPdf'])->name('board-results.upload-pdf');
    Route::delete('/board-results/{boardResult}',                  [BoardResultController::class, 'destroy'])->name('board-results.destroy');
    Route::get('/board-results/{boardResult}/toppers',             [BoardResultController::class, 'toppers'])->name('board-results.toppers');
    Route::post('/board-results/{boardResult}/toppers',            [BoardResultController::class, 'storeTopper'])->name('board-results.toppers.store');
    Route::put('/board-results/{boardResult}/toppers/{topper}',    [BoardResultController::class, 'updateTopper'])->name('board-results.toppers.update');
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
    }); // public.website.admin.cms
    }); // website.enabled
});

// ── Sahodaya Admin Panel ─────────────────────────────────────────────────────
Route::prefix('sahodaya-admin/{tenantId}')
    ->name('sahodaya.')
    ->middleware(['web', 'auth', 'password.change', 'sahodaya.admin', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {

        // Dashboard
        Route::get('/', [\App\Http\Controllers\SahodayaAdmin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [\App\Http\Controllers\SahodayaAdmin\TenantUserController::class, 'index'])->name('users.index');
        Route::post('/users', [\App\Http\Controllers\SahodayaAdmin\TenantUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [\App\Http\Controllers\SahodayaAdmin\TenantUserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\SahodayaAdmin\TenantUserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/users/{user}', [\App\Http\Controllers\SahodayaAdmin\TenantUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/settings/nav-visibility', [\App\Http\Controllers\SahodayaAdmin\NavVisibilityController::class, 'edit'])->name('settings.nav-visibility');
        Route::put('/settings/nav-visibility', [\App\Http\Controllers\SahodayaAdmin\NavVisibilityController::class, 'update'])->name('settings.nav-visibility.update');

        // ── Academic Year Management (Phase 8) ────────────────────────────────
        Route::prefix('academic-years')->name('academic-years.')->group(function () {
            Route::get('/',                                        [AcademicYearController::class, 'index'])->name('index');
            Route::post('/',                                       [AcademicYearController::class, 'store'])->name('store');
            Route::post('/{academicYear}/activate',                [AcademicYearController::class, 'activate'])->name('activate');
            Route::post('/{academicYear}/close',                   [AcademicYearController::class, 'close'])->name('close');
            Route::post('/financial-years',                        [AcademicYearController::class, 'storeFinancialYear'])->name('financial.store');
            Route::post('/financial-years/{financialYear}/current',[AcademicYearController::class, 'setCurrentFinancialYear'])->name('financial.current');
        });

        Route::prefix('sports-age-groups')->name('sports-age-groups.')->group(function () {
            Route::get('/', fn (string $tenantId) => redirect("/sahodaya-admin/{$tenantId}/sports/age-groups", 301));
            Route::post('/', [SportsAgeGroupController::class, 'store'])->name('store');
            Route::post('/reset-defaults', [SportsAgeGroupController::class, 'resetDefaults'])->name('reset-defaults');
            Route::put('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'update'])->name('update');
            Route::delete('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('competition-types')->name('competition-types.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionTypeController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionTypeController::class, 'store'])->name('store');
            Route::post('/reset-defaults', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionTypeController::class, 'resetDefaults'])->name('reset-defaults');
            Route::put('/{competitionType}', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionTypeController::class, 'update'])->name('update');
            Route::delete('/{competitionType}', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionTypeController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('taxonomy-masters')->name('taxonomy-masters.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\FestTaxonomyMasterController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SahodayaAdmin\FestTaxonomyMasterController::class, 'store'])->name('store');
            Route::post('/reset-defaults', [\App\Http\Controllers\SahodayaAdmin\FestTaxonomyMasterController::class, 'resetDefaults'])->name('reset-defaults');
            Route::put('/{taxonomyMaster}', [\App\Http\Controllers\SahodayaAdmin\FestTaxonomyMasterController::class, 'update'])->name('update');
            Route::delete('/{taxonomyMaster}', [\App\Http\Controllers\SahodayaAdmin\FestTaxonomyMasterController::class, 'destroy'])->name('destroy');
        });

        // Notification templates — communications config, not part of the public
        // website/CMS module, so it must stay reachable even when the website
        // feature is disabled (WEBSITE_ENABLED=false). Previously nested under
        // the website.enabled middleware below, which made this page 404 for any
        // Sahodaya with the website feature off.
        Route::get('/notification-templates', [\App\Http\Controllers\SahodayaAdmin\NotificationTemplateController::class, 'index'])->name('notification-templates.index');
        Route::put('/notification-templates/{template}', [\App\Http\Controllers\SahodayaAdmin\NotificationTemplateController::class, 'update'])->name('notification-templates.update');
        Route::post('/notification-templates/{template}/test', [\App\Http\Controllers\SahodayaAdmin\NotificationTemplateController::class, 'sendTest'])->name('notification-templates.test');

        // Website & CMS (disabled until WEBSITE_ENABLED=true)
        Route::middleware('website.enabled')->group(function () {
        Route::middleware('public.website.admin.cms')->group(function () {
        Route::get('/site-builder', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderController::class, 'index'])->name('site-builder');
        Route::get('/site-builder/section-types', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderController::class, 'sectionTypes'])->name('site-builder.section-types');
        Route::get('/website/domains', [\App\Http\Controllers\SahodayaAdmin\WebsiteDomainController::class, 'index'])->name('website.domains');
        Route::post('/website/domains', [\App\Http\Controllers\SahodayaAdmin\WebsiteDomainController::class, 'store'])->name('website.domains.store');
        Route::post('/website/domains/{domainId}/verify', [\App\Http\Controllers\SahodayaAdmin\WebsiteDomainController::class, 'verify'])->name('website.domains.verify');
        Route::delete('/website/domains/{domainId}', [\App\Http\Controllers\SahodayaAdmin\WebsiteDomainController::class, 'destroy'])->name('website.domains.destroy');
        Route::get('/website/sites', [\App\Http\Controllers\SahodayaAdmin\WebsiteSiteController::class, 'index'])->name('website.sites');
        Route::post('/website/sites', [\App\Http\Controllers\SahodayaAdmin\WebsiteSiteController::class, 'store'])->name('website.sites.store');
        Route::put('/website/sites/{site}', [\App\Http\Controllers\SahodayaAdmin\WebsiteSiteController::class, 'update'])->name('website.sites.update');
        Route::delete('/website/sites/{site}', [\App\Http\Controllers\SahodayaAdmin\WebsiteSiteController::class, 'destroy'])->name('website.sites.destroy');
        Route::get('/website/forms', [\App\Http\Controllers\SahodayaAdmin\SiteFormController::class, 'index'])->name('website.forms');
        Route::post('/website/forms', [\App\Http\Controllers\SahodayaAdmin\SiteFormController::class, 'store'])->name('website.forms.store');
        Route::put('/website/forms/{form}', [\App\Http\Controllers\SahodayaAdmin\SiteFormController::class, 'update'])->name('website.forms.update');
        Route::delete('/website/forms/{form}', [\App\Http\Controllers\SahodayaAdmin\SiteFormController::class, 'destroy'])->name('website.forms.destroy');
        Route::get('/website/forms/{form}/submissions', [\App\Http\Controllers\SahodayaAdmin\SiteFormController::class, 'submissions'])->name('website.forms.submissions');

        Route::prefix('site-builder/api')->name('site-builder.api.')->group(function () {
            Route::get('/sections', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'sections'])->name('sections.index');
            Route::post('/sections', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'storeSection'])->name('sections.store');
            Route::patch('/sections/{sectionId}', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'updateSection'])->name('sections.update');
            Route::delete('/sections/{sectionId}', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'deleteSection'])->name('sections.delete');
            Route::post('/sections/{sectionId}/toggle', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'toggleSection'])->name('sections.toggle');
            Route::post('/sections/reorder', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'reorderSections'])->name('sections.reorder');
            Route::get('/nav', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'getNav'])->name('nav.get');
            Route::post('/nav', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'saveNav'])->name('nav.save');
            Route::get('/footer', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'getFooter'])->name('footer.get');
            Route::post('/footer', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'saveFooter'])->name('footer.save');
            Route::post('/portal-links', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'ensurePortalLinks'])->name('portal-links.ensure');
            Route::post('/default-nav', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'ensureDefaultNav'])->name('default-nav.ensure');
            Route::post('/apply-cksc-template', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'applyCkscTemplate'])->name('cksc-template.apply');
            Route::post('/sections/{sectionId}/publish', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'publishSection'])->name('sections.publish');
            Route::get('/sections/{sectionId}/versions', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'sectionVersions'])->name('sections.versions');
            Route::post('/sections/{sectionId}/versions/{versionId}/restore', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'restoreSectionVersion'])->name('sections.versions.restore');
            Route::get('/public-website', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'getPublicWebsite'])->name('public-website.get');
            Route::post('/public-website', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'savePublicWebsite'])->name('public-website.save');
            Route::get('/theme', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'getTheme'])->name('theme.get');
            Route::post('/theme', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'saveTheme'])->name('theme.save');
            Route::post('/media', [\App\Http\Controllers\SahodayaAdmin\SiteBuilderApiController::class, 'uploadMedia'])->name('media.upload');
        });

        Route::get('/office-bearers',              [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'index'])->name('office-bearers.index');
        Route::post('/office-bearers',             [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'store'])->name('office-bearers.store');
        Route::put('/office-bearers/{bearer}',     [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'update'])->name('office-bearers.update');
        Route::delete('/office-bearers/{bearer}',  [\App\Http\Controllers\SahodayaAdmin\OfficeBearersController::class, 'destroy'])->name('office-bearers.destroy');

        Route::get('/circulars',              [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'index'])->name('circulars.index');
        Route::post('/circulars',             [\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'store'])->name('circulars.store');
        Route::delete('/circulars/{circular}',[\App\Http\Controllers\SahodayaAdmin\CircularController::class, 'destroy'])->name('circulars.destroy');
        }); // public.website.admin.cms
        }); // website.enabled

        // Portal & website content (portal always available; full website tabs when enabled)
        Route::get('/public-content',  [\App\Http\Controllers\SahodayaAdmin\PublicContentController::class, 'index'])->name('public-content.index');
        Route::put('/public-content',  [\App\Http\Controllers\SahodayaAdmin\PublicContentController::class, 'update'])->name('public-content.update');

        // Membership & registration (always available)
        Route::get('/setup', [\App\Http\Controllers\SahodayaAdmin\SetupWizardController::class, 'show'])->name('setup.wizard');
        Route::post('/setup/complete', [\App\Http\Controllers\SahodayaAdmin\SetupWizardController::class, 'complete'])->name('setup.complete');
        Route::post('/setup/dismiss', [\App\Http\Controllers\SahodayaAdmin\SetupWizardController::class, 'dismiss'])->name('setup.dismiss');

        Route::get('/schools', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'index'])->name('schools.index');
        Route::get('/schools/applications', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'applications'])->name('schools.applications');
        Route::post('/schools/applications/bulk-approve', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'bulkApprove'])->name('schools.applications.bulk-approve');
        Route::post('/schools/applications/bulk-reject', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'bulkReject'])->name('schools.applications.bulk-reject');
        Route::post('/schools/bulk-cancel-membership', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'bulkCancelMembership'])->name('schools.bulk-cancel-membership');
        Route::get('/schools/export', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'export'])->name('schools.export');
        Route::get('/schools/{school}/students', [\App\Http\Controllers\SahodayaAdmin\SchoolStudentsController::class, 'show'])->name('schools.students');
        Route::get('/schools/{school}', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'show'])->name('schools.show');
        Route::get('/schools/{school}/lock-overrides', [\App\Http\Controllers\SahodayaAdmin\SchoolLockOverrideController::class, 'index'])->name('schools.lock-overrides');
        Route::post('/schools/{school}/lock-overrides', [\App\Http\Controllers\SahodayaAdmin\SchoolLockOverrideController::class, 'store'])->name('schools.lock-overrides.store');
        Route::post('/schools/{school}/approve', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'approve'])->name('schools.approve');
        Route::post('/schools/{school}/reject', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'reject'])->name('schools.reject');
        Route::post('/schools/{school}/cancel-membership', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'cancelMembership'])->name('schools.cancel-membership');
        Route::post('/schools/{school}/toggle-fest-registration', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'toggleFestRegistration'])->name('schools.toggle-fest-registration');
        Route::delete('/schools/{school}', [\App\Http\Controllers\SahodayaAdmin\MemberSchoolsController::class, 'destroy'])->name('schools.destroy');

        // Regions (Kalotsav) — State → Sahodaya → Region → School
        Route::get('/regions', [\App\Http\Controllers\SahodayaAdmin\RegionController::class, 'index'])->name('regions.index');
        Route::post('/regions', [\App\Http\Controllers\SahodayaAdmin\RegionController::class, 'store'])->name('regions.store');
        Route::post('/regions/assign', [\App\Http\Controllers\SahodayaAdmin\RegionController::class, 'assign'])->name('regions.assign');
        Route::put('/regions/{region}', [\App\Http\Controllers\SahodayaAdmin\RegionController::class, 'update'])->name('regions.update');
        Route::delete('/regions/{region}', [\App\Http\Controllers\SahodayaAdmin\RegionController::class, 'destroy'])->name('regions.destroy');

        // Membership settings
        Route::get('/membership/settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'index'])->name('membership.settings');
        Route::put('/membership/settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateProfile'])->name('membership.settings.update');
        Route::get('/settings/storage-migration', [\App\Http\Controllers\SahodayaAdmin\StorageMigrationController::class, 'index'])->name('settings.storage-migration');
        Route::get('/settings/storage-migration/scan', [\App\Http\Controllers\SahodayaAdmin\StorageMigrationController::class, 'scan'])->name('settings.storage-migration.scan');
        Route::post('/settings/storage-migration/migrate', [\App\Http\Controllers\SahodayaAdmin\StorageMigrationController::class, 'migrate'])->name('settings.storage-migration.migrate');
        Route::get('/settings/storage-migration/progress', [\App\Http\Controllers\SahodayaAdmin\StorageMigrationController::class, 'progress'])->name('settings.storage-migration.progress');
        Route::get('/student-change-requests', [\App\Http\Controllers\SahodayaAdmin\StudentEditChangeRequestController::class, 'index'])->name('student-change-requests.index');
        Route::post('/student-change-requests/{changeRequest}/approve', [\App\Http\Controllers\SahodayaAdmin\StudentEditChangeRequestController::class, 'approve'])->name('student-change-requests.approve');
        Route::post('/student-change-requests/{changeRequest}/reject', [\App\Http\Controllers\SahodayaAdmin\StudentEditChangeRequestController::class, 'reject'])->name('student-change-requests.reject');
        Route::get('/students/registration-windows', [\App\Http\Controllers\SahodayaAdmin\StudentRegistrationWindowController::class, 'index'])->name('students.registration-windows');
        Route::post('/students/registration-windows', [\App\Http\Controllers\SahodayaAdmin\StudentRegistrationWindowController::class, 'update'])->name('students.registration-windows.update');
        Route::get('/users/profile-change-requests', [\App\Http\Controllers\SahodayaAdmin\UserProfileChangeRequestController::class, 'index'])->name('users.profile-change-requests');
        Route::post('/users/profile-change-requests/{changeRequest}/approve', [\App\Http\Controllers\SahodayaAdmin\UserProfileChangeRequestController::class, 'approve'])->name('users.profile-change-requests.approve');
        Route::post('/users/profile-change-requests/{changeRequest}/reject', [\App\Http\Controllers\SahodayaAdmin\UserProfileChangeRequestController::class, 'reject'])->name('users.profile-change-requests.reject');

        Route::get('/students/verification', [\App\Http\Controllers\SahodayaAdmin\StudentVerificationController::class, 'index'])->name('students.verification.index');
        Route::post('/students/verification/bulk-verify', [\App\Http\Controllers\SahodayaAdmin\StudentVerificationController::class, 'bulkVerify'])->name('students.verification.bulk');
        Route::post('/students/verification/bulk-reject', [\App\Http\Controllers\SahodayaAdmin\StudentVerificationController::class, 'bulkReject'])->name('students.verification.bulk-reject');
        Route::get('/students/{student}', [\App\Http\Controllers\SahodayaAdmin\StudentProfileController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/photo', [\App\Http\Controllers\SahodayaAdmin\StudentProfileController::class, 'showPhoto'])->name('students.photo');
        Route::post('/students/{student}/portal-login', [\App\Http\Controllers\SahodayaAdmin\StudentProfileController::class, 'provisionPortal'])->name('students.portal-login');
        Route::post('/students/{student}/reset-portal-password', [\App\Http\Controllers\SahodayaAdmin\StudentProfileController::class, 'resetPortalPassword'])->name('students.reset-portal-password');
        Route::post('/students/{student}/verify', [\App\Http\Controllers\SahodayaAdmin\StudentVerificationController::class, 'verify'])->name('students.verification.verify');
        Route::post('/students/{student}/reject', [\App\Http\Controllers\SahodayaAdmin\StudentVerificationController::class, 'reject'])->name('students.verification.reject');

        Route::get('/teachers/verification', [\App\Http\Controllers\SahodayaAdmin\TeacherVerificationController::class, 'index'])->name('teachers.verification.index');
        Route::post('/teachers/verification/bulk-verify', [\App\Http\Controllers\SahodayaAdmin\TeacherVerificationController::class, 'bulkVerify'])->name('teachers.verification.bulk');
        Route::post('/teachers/{teacher}/verify', [\App\Http\Controllers\SahodayaAdmin\TeacherVerificationController::class, 'verify'])->name('teachers.verification.verify');
        Route::post('/teachers/{teacher}/reject', [\App\Http\Controllers\SahodayaAdmin\TeacherVerificationController::class, 'reject'])->name('teachers.verification.reject');

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\FinanceHubController::class, 'index'])->name('hub');
            Route::get('/payments', [\App\Http\Controllers\SahodayaAdmin\UnifiedPaymentsController::class, 'index'])->name('payments.index');
            Route::get('/payments/export', [\App\Http\Controllers\SahodayaAdmin\UnifiedPaymentsController::class, 'export'])->name('payments.export');
            Route::get('/payments/receipts/{feeReceipt}', [\App\Http\Controllers\SahodayaAdmin\UnifiedPaymentsController::class, 'programReceipt'])->name('payments.receipt');
            Route::post('/payments/resend-receipt', [\App\Http\Controllers\SahodayaAdmin\UnifiedPaymentsController::class, 'resendReceipt'])->name('payments.resend-receipt');
            Route::post('/payments/receipts/{feeReceipt}/reverse', [\App\Http\Controllers\SahodayaAdmin\UnifiedPaymentsController::class, 'reverseReceipt'])->name('payments.reverse');
            Route::get('/receipt-emails', [\App\Http\Controllers\SahodayaAdmin\ReceiptEmailReportController::class, 'index'])->name('receipt-emails');
            Route::get('/email-delivery', [\App\Http\Controllers\SahodayaAdmin\EmailDeliveryReportController::class, 'index'])->name('email-delivery');
            Route::post('/email-delivery/{notificationLog}/retry', [\App\Http\Controllers\SahodayaAdmin\EmailDeliveryReportController::class, 'retry'])->name('email-delivery.retry');
            Route::post('/receipt-emails/{feeReceipt}/resend', [\App\Http\Controllers\SahodayaAdmin\ReceiptEmailReportController::class, 'resend'])->name('receipt-emails.resend');
            Route::get('/receivables', [\App\Http\Controllers\SahodayaAdmin\FinanceHubController::class, 'receivables'])->name('receivables');
            Route::get('/financial-statements', [\App\Http\Controllers\SahodayaAdmin\LedgerController::class, 'financialStatements'])->name('financial-statements');
            Route::post('/fee-waiver', [\App\Http\Controllers\SahodayaAdmin\LedgerController::class, 'waiveFee'])->name('fee-waiver');
            Route::get('/bank-reconciliation', [\App\Http\Controllers\SahodayaAdmin\BankReconciliationController::class, 'index'])->name('bank-reconciliation');
            Route::post('/bank-reconciliation/{transaction}/reconcile', [\App\Http\Controllers\SahodayaAdmin\BankReconciliationController::class, 'reconcile'])->name('bank-reconciliation.reconcile');
            Route::get('/payables', [\App\Http\Controllers\SahodayaAdmin\PayableController::class, 'index'])->name('payables.index');
            Route::post('/payables', [\App\Http\Controllers\SahodayaAdmin\PayableController::class, 'store'])->name('payables.store');
            Route::post('/payables/{payable}/pay', [\App\Http\Controllers\SahodayaAdmin\PayableController::class, 'markPaid'])->name('payables.pay');
            Route::post('/payables/{payable}/cancel', [\App\Http\Controllers\SahodayaAdmin\PayableController::class, 'cancel'])->name('payables.cancel');
        });
        Route::put('/membership/fees', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMembershipFees'])->name('membership.fees.update');
        Route::put('/membership/payment-details', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updatePaymentDetails'])->name('membership.payment-details.update');
        Route::put('/membership/mail-settings', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMailSettings'])->name('membership.mail-settings.update');
        Route::post('/membership/mail-settings/test', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'testMailSettings'])->name('membership.mail-settings.test');
        Route::post('/membership/fee-slabs', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeFeeSlab'])->name('membership.fee-slabs.store');
        Route::delete('/membership/fee-slabs/{feeSlab}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyFeeSlab'])->name('membership.fee-slabs.destroy');
        Route::put('/membership/registration-window', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateRegistrationWindow'])->name('membership.window.update');
        Route::post('/membership/custom-categories', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeCustomCategory'])->name('membership.custom-categories.store');
        Route::put('/membership/custom-categories/{classCategoryId}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateCustomCategory'])->name('membership.custom-categories.update')->whereNumber('classCategoryId');
        Route::delete('/membership/custom-categories/{classCategoryId}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyCustomCategory'])->name('membership.custom-categories.destroy')->whereNumber('classCategoryId');
        Route::post('/membership/classes', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeMasterClass'])->name('membership.classes.store');
        Route::put('/membership/classes/{masterClassId}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateMasterClass'])->name('membership.classes.update')->whereNumber('masterClassId');
        Route::delete('/membership/classes/{masterClassId}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroyMasterClass'])->name('membership.classes.destroy')->whereNumber('masterClassId');
        Route::post('/membership/category-overrides', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'toggleCategoryOverride'])->name('membership.category-overrides.toggle');
        Route::put('/membership/global-categories/{classCategoryId}/sort', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateGlobalCategorySort'])->name('membership.global-categories.sort')->whereNumber('classCategoryId');
        Route::post('/membership/custom-teaching-types', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeCustomTeachingType'])->name('membership.custom-types.store');
        Route::post('/membership/type-overrides', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'toggleTypeOverride'])->name('membership.type-overrides.toggle');
        Route::post('/membership/subjects', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'storeSubject'])->name('membership.subjects.store');
        Route::put('/membership/subjects/{subject}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateSubject'])->name('membership.subjects.update');
        Route::delete('/membership/subjects/{subject}', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'destroySubject'])->name('membership.subjects.destroy');
        Route::put('/membership/receipt-template', [\App\Http\Controllers\SahodayaAdmin\MembershipReceiptController::class, 'updateTemplate'])->name('membership.receipt-template.update');
        Route::post('/membership/receipt-template/assets', [\App\Http\Controllers\SahodayaAdmin\MembershipReceiptController::class, 'uploadAsset'])->name('membership.receipt-template.assets');
        Route::get('/membership/receipt-template/preview', [\App\Http\Controllers\SahodayaAdmin\MembershipReceiptController::class, 'preview'])->name('membership.receipt-template.preview');
        Route::get('/membership/payments/{payment}/receipt', [\App\Http\Controllers\SahodayaAdmin\MembershipReceiptController::class, 'show'])->name('membership.payments.receipt');

        // Submission review
        Route::post('/membership/submissions/bulk-approve', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'bulkApprove'])->name('membership.submissions.bulk-approve');
        Route::post('/membership/submissions/approve-all-pending', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'approveAllPending'])->name('membership.submissions.approve-all-pending');
        Route::get('/membership/submissions', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'index'])->name('membership.submissions.index');
        Route::get('/membership/submissions/{submission}', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'show'])->name('membership.submissions.show');
        Route::post('/membership/submissions/{submission}/approve-track', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'approveTrack'])->name('membership.submissions.approve-track');
        Route::post('/membership/submissions/{submission}/reject-track', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'rejectTrack'])->name('membership.submissions.reject-track');
        Route::get('/membership/submission-students/{student}/image', [\App\Http\Controllers\SahodayaAdmin\SubmissionReviewController::class, 'showSubmissionStudentImage'])->name('membership.submission-students.image');

        // Payment verification
        Route::get('/membership/payments', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'index'])->name('membership.payments.index');
        Route::get('/membership/payments/export', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'export'])->name('membership.payments.export');
        Route::get('/membership/payments/{payment}/proof', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'proof'])->name('membership.payments.proof');
        Route::post('/membership/payments/{payment}/verify', [\App\Http\Controllers\SahodayaAdmin\PaymentVerificationController::class, 'verify'])->name('membership.payments.verify');

        Route::get('/membership/reports', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'index'])->name('membership.reports');
        Route::get('/reports/hub', [\App\Http\Controllers\SahodayaAdmin\ReportsHubController::class, 'index'])->name('reports.hub');
        Route::get('/reports/{reportId}', [\App\Http\Controllers\SahodayaAdmin\ErpReportController::class, 'show'])->name('reports.run');
        Route::get('/reports/{reportId}/export', [\App\Http\Controllers\SahodayaAdmin\ErpReportController::class, 'export'])->name('reports.export');

        Route::get('/auth/login-audit', [\App\Http\Controllers\SahodayaAdmin\LoginAuditController::class, 'index'])->name('auth.login-audit');
        Route::get('/auth/login-audit/export', [\App\Http\Controllers\SahodayaAdmin\LoginAuditController::class, 'export'])->name('auth.login-audit.export');

        Route::get('/calendar', [\App\Http\Controllers\SahodayaAdmin\CalendarController::class, 'index'])->name('calendar');
        Route::get('/calendar/export.ics', [\App\Http\Controllers\SahodayaAdmin\CalendarController::class, 'exportIcal'])->name('calendar.ical');

        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/review', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentReviewController::class, 'index'])->name('review');
            Route::get('/types', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentTypeController::class, 'index'])->name('types');
            Route::post('/types', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentTypeController::class, 'store'])->name('types.store');
            Route::put('/types/{documentType}', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentTypeController::class, 'update'])->name('types.update');
            Route::delete('/types/{documentType}', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentTypeController::class, 'destroy'])->name('types.destroy');
            Route::post('/{document}/approve', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentReviewController::class, 'approve'])->name('approve');
            Route::post('/{document}/reject', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentReviewController::class, 'reject'])->name('reject');
            Route::get('/{document}/download', [\App\Http\Controllers\SahodayaAdmin\SchoolDocumentReviewController::class, 'download'])->name('download');
        });

        Route::prefix('board-results')->name('board-results.')->group(function () {
            Route::get('/verification', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'index'])->name('verification');
            Route::get('/masters', [\App\Http\Controllers\SahodayaAdmin\BoardResultMastersController::class, 'index'])->name('masters');
            Route::post('/masters/streams', [\App\Http\Controllers\SahodayaAdmin\BoardResultMastersController::class, 'storeStream'])->name('masters.streams.store');
            Route::put('/masters/streams/{stream}', [\App\Http\Controllers\SahodayaAdmin\BoardResultMastersController::class, 'updateStream'])->name('masters.streams.update');
            Route::delete('/masters/streams/{stream}', [\App\Http\Controllers\SahodayaAdmin\BoardResultMastersController::class, 'destroyStream'])->name('masters.streams.destroy');
            Route::put('/masters/api-config', [\App\Http\Controllers\SahodayaAdmin\BoardResultMastersController::class, 'updateApiConfig'])->name('masters.api-config');
            Route::get('/reports', [\App\Http\Controllers\SahodayaAdmin\BoardResultReportController::class, 'index'])->name('reports');
            Route::get('/reports/subject-merit', [\App\Http\Controllers\SahodayaAdmin\BoardResultReportController::class, 'subjectMerit'])->name('reports.subject-merit');
            Route::get('/reports/excellence', [\App\Http\Controllers\SahodayaAdmin\BoardResultReportController::class, 'excellence'])->name('reports.excellence');
            Route::post('/topper-cap', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'updateTopperCap'])->name('topper-cap');
            Route::post('/{boardResult}/verify', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'verify'])->name('verify');
            Route::post('/{boardResult}/approve', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'approve'])->name('approve');
            Route::post('/{boardResult}/reject', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'reject'])->name('reject');
            Route::post('/{boardResult}/publish', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'publish'])->name('publish');
            Route::get('/{boardResult}/pdf', [\App\Http\Controllers\SahodayaAdmin\BoardResultVerificationController::class, 'downloadPdf'])->name('pdf');
        });
        Route::get('/membership/reports/export/schools', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportSchools'])->name('membership.reports.export.schools');
        Route::get('/membership/reports/export/approved-unpaid', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportApprovedUnpaid'])->name('membership.reports.export.approved-unpaid');
        Route::get('/membership/reports/export/payments-pending', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPaymentsPending'])->name('membership.reports.export.payments-pending');
        Route::get('/membership/reports/export/payment-due', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPaymentDue'])->name('membership.reports.export.payment-due');
        Route::get('/membership/reports/export/payments-done', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPaymentsDone'])->name('membership.reports.export.payments-done');
        Route::get('/membership/reports/export/submissions', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportSubmissions'])->name('membership.reports.export.submissions');
        Route::get('/membership/reports/export/payments', [\App\Http\Controllers\SahodayaAdmin\MembershipReportsController::class, 'exportPayments'])->name('membership.reports.export.payments');

        require __DIR__.'/includes/sahodaya_event_programs.php';

        // Head-first permalink: always resolves to this sport's own Competition page,
        // regardless of which discipline event id it currently lives on.
        Route::get('/sports/heads/{head}', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'showByHead'])->name('sports.heads.show');

        // ── Operational modules (Phases 11–16) ────────────────────────────────
        Route::prefix('events')->name('events.')->group(function () {
            Route::get('/', [FestEventController::class, 'index'])->name('index');
            Route::post('/', [FestEventController::class, 'store'])->name('store');
            Route::get('/{event}/items/list', [FestEventController::class, 'itemsList'])->name('items.list');
            Route::get('/{event}/items', [FestEventController::class, 'items'])->name('items.index');
            Route::get('/{event}/levels', [FestEventController::class, 'levels'])->name('levels');
            Route::get('/{event}/activity', [\App\Http\Controllers\SahodayaAdmin\FestEventActivityController::class, 'index'])->name('activity');
            Route::post('/{event}/toggle-nav-hidden', [FestEventController::class, 'toggleNavHidden'])->name('toggle-nav-hidden');
            Route::post('/{event}/fix-mistaken-season', [FestEventController::class, 'fixMistakenSeason'])->name('fix-mistaken-season');
            Route::get('/{event}', [FestEventController::class, 'show'])->name('show');
            Route::put('/{event}', [FestEventController::class, 'update'])->name('update');
            Route::delete('/{event}', [FestEventController::class, 'destroy'])->name('destroy');
            Route::post('/{event}/spawn', [FestEventController::class, 'spawnCascade'])->name('spawn');
            Route::post('/{event}/spawn-cluster', [FestEventController::class, 'spawnCluster'])->name('spawn-cluster');
            Route::post('/{event}/spawn-partition', [FestEventController::class, 'spawnPartition'])->name('spawn-partition');
            Route::post('/{event}/apply-conduct-preset', [FestEventController::class, 'applyConductPreset'])->name('apply-conduct-preset');
            Route::post('/{event}/assign-school-partitions', [FestEventController::class, 'assignSchoolPartitions'])->name('assign-school-partitions');
            Route::post('/{event}/sync-region-partitions', [FestEventController::class, 'syncRegionPartitions'])->name('sync-region-partitions');
            Route::post('/{event}/submit-state-qualifiers', [\App\Http\Controllers\SahodayaAdmin\StateQualifierSubmissionController::class, 'store'])->name('submit-state-qualifiers');
            Route::post('/{event}/spawn-school-rounds', [FestEventController::class, 'spawnSchoolRounds'])->name('spawn-school-rounds');
            Route::post('/{event}/link-school-round', [FestEventController::class, 'linkSchoolRound'])->name('link-school-round');
            Route::post('/{event}/promote-discipline-events', [FestEventController::class, 'promoteDisciplineEvents'])->name('promote-discipline-events');
            Route::post('/{event}/promote-all-school-rounds', [FestEventController::class, 'promoteAllSchoolRounds'])->name('promote-all-school-rounds');
            Route::post('/{event}/items', [FestEventController::class, 'storeItem'])->name('items.store');
            Route::put('/{event}/items/{item}', [FestEventController::class, 'updateItem'])->name('items.update');
            Route::post('/{event}/items/import-catalog', [FestEventController::class, 'importCatalog'])->name('items.import-catalog');
            Route::delete('/{event}/items/{item}', [FestEventController::class, 'destroyItem'])->name('items.destroy');
            Route::get('/{event}/registrations/import', [FestRegistrationReviewController::class, 'importForm'])->name('registrations.import-form');
            Route::get('/{event}/registrations', [FestRegistrationReviewController::class, 'index'])->name('registrations.index');
            Route::post('/{event}/registrations/on-behalf', [FestRegistrationReviewController::class, 'storeOnBehalf'])->name('registrations.on-behalf');
            Route::post('/{event}/registrations/bulk-approve', [FestRegistrationReviewController::class, 'bulkApprove'])->name('registrations.bulk-approve');
            Route::post('/{event}/registrations/bulk-reject', [FestRegistrationReviewController::class, 'bulkReject'])->name('registrations.bulk-reject');
            Route::get('/{event}/registrations/import-template', [FestRegistrationReviewController::class, 'importTemplate'])->name('registrations.import-template');
            Route::post('/{event}/registrations/import', [FestRegistrationReviewController::class, 'importStore'])->name('registrations.import');
            Route::post('/{event}/registrations/{registration}/approve', [FestRegistrationReviewController::class, 'approve'])->name('registrations.approve');
            Route::post('/{event}/registrations/{registration}/reject', [FestRegistrationReviewController::class, 'reject'])->name('registrations.reject');
            Route::post('/{event}/registrations/{registration}/cancel', [FestRegistrationReviewController::class, 'cancel'])->name('registrations.cancel');
            Route::post('/{event}/registrations/{registration}/substitute/{performer}/{standby}', [FestRegistrationReviewController::class, 'substitute'])->name('registrations.substitute');
            Route::get('/{event}/substitution-requests', [\App\Http\Controllers\SahodayaAdmin\FestSubstitutionReviewController::class, 'index'])->name('substitution-requests.index');
            Route::post('/{event}/substitution-requests/{substitutionRequest}/approve', [\App\Http\Controllers\SahodayaAdmin\FestSubstitutionReviewController::class, 'approve'])->name('substitution-requests.approve');
            Route::post('/{event}/substitution-requests/{substitutionRequest}/reject', [\App\Http\Controllers\SahodayaAdmin\FestSubstitutionReviewController::class, 'reject'])->name('substitution-requests.reject');
            Route::get('/{event}/clash-requests', [\App\Http\Controllers\SahodayaAdmin\FestClashReviewController::class, 'index'])->name('clash-requests.index');
            Route::post('/{event}/clash-requests/{clashRequest}/approve', [\App\Http\Controllers\SahodayaAdmin\FestClashReviewController::class, 'approve'])->name('clash-requests.approve');
            Route::post('/{event}/clash-requests/{clashRequest}/reject', [\App\Http\Controllers\SahodayaAdmin\FestClashReviewController::class, 'reject'])->name('clash-requests.reject');
            Route::post('/{event}/school-verifications/{schoolId}', [\App\Http\Controllers\SahodayaAdmin\FestSchoolVerificationController::class, 'verify'])->name('school-verifications.verify');
            Route::get('/{event}/attendance', [FestAttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/{event}/attendance', [FestAttendanceController::class, 'store'])->name('attendance.store');
            Route::get('/{event}/attendance/import-template', [FestAttendanceController::class, 'importTemplate'])->name('attendance.import-template');
            Route::post('/{event}/attendance/import', [FestAttendanceController::class, 'importStore'])->name('attendance.import');
            Route::get('/{event}/schedule', [FestScheduleController::class, 'index'])->name('schedule.index');
            Route::post('/{event}/schedule', [FestScheduleController::class, 'store'])->name('schedule.store');
            Route::post('/{event}/schedule/auto', [FestScheduleController::class, 'autoGenerate'])->name('schedule.auto');
            Route::post('/{event}/schedule/publish', [FestScheduleController::class, 'publishSchedule'])->name('schedule.publish');
            Route::post('/{event}/schedule/unpublish', [FestScheduleController::class, 'unpublishSchedule'])->name('schedule.unpublish');
            Route::get('/{event}/schedule/import-template', [FestScheduleController::class, 'importTemplate'])->name('schedule.import-template');
            Route::post('/{event}/schedule/import', [FestScheduleController::class, 'importStore'])->name('schedule.import');
            Route::get('/{event}/schedule/items', [FestScheduleController::class, 'itemsIndex'])->name('schedule.items');
            Route::post('/{event}/schedule/items/bulk', [FestScheduleController::class, 'bulkStoreItems'])->name('schedule.items.bulk');
            Route::get('/{event}/schedule/items/import-template', [FestScheduleController::class, 'itemImportTemplate'])->name('schedule.items.import-template');
            Route::post('/{event}/schedule/items/import', [FestScheduleController::class, 'itemImportStore'])->name('schedule.items.import');
            Route::delete('/{event}/schedule/{schedule}', [FestScheduleController::class, 'destroy'])->name('schedule.destroy');
            Route::post('/{event}/schedule/{schedule}/reorder', [FestScheduleController::class, 'reorder'])->name('schedule.reorder');
            Route::get('/{event}/judges', [FestJudgeAssignmentController::class, 'index'])->name('judges.index');
            Route::post('/{event}/judges', [FestJudgeAssignmentController::class, 'store'])->name('judges.store');
            Route::delete('/{event}/judges/{assignment}', [FestJudgeAssignmentController::class, 'destroy'])->name('judges.destroy');
            Route::get('/{event}/event-staff', [\App\Http\Controllers\SahodayaAdmin\FestEventStaffController::class, 'index'])->name('event-staff.index');
            Route::post('/{event}/event-staff', [\App\Http\Controllers\SahodayaAdmin\FestEventStaffController::class, 'store'])->name('event-staff.store');
            Route::delete('/{event}/event-staff/{assignment}', [\App\Http\Controllers\SahodayaAdmin\FestEventStaffController::class, 'destroy'])->name('event-staff.destroy');
            Route::get('/{event}/marks', [FestMarkEntryController::class, 'index'])->name('marks.index');
            Route::post('/{event}/marks', [FestMarkEntryController::class, 'store'])->name('marks.store');
            Route::post('/{event}/items/{item}/auto-rank', [FestMarkEntryController::class, 'autoRankItem'])->name('items.auto-rank');
            Route::get('/{event}/results', [FestResultsController::class, 'show'])->name('results.show');
            Route::post('/{event}/results/publish', [FestResultsController::class, 'publish'])->name('results.publish');
            Route::post('/{event}/results/unpublish', [FestResultsController::class, 'unpublish'])->name('results.unpublish');
            Route::post('/{event}/results/items/bulk-publish', [FestResultsController::class, 'bulkPublishItems'])->name('results.items.bulk-publish');
            Route::post('/{event}/results/items/{item}/publish', [FestResultsController::class, 'publishItem'])->name('results.items.publish');
            Route::post('/{event}/results/items/{item}/unpublish', [FestResultsController::class, 'unpublishItem'])->name('results.items.unpublish');
            Route::post('/{event}/results/promote', [FestResultsController::class, 'promote'])->name('results.promote');
            Route::post('/{event}/results/promote-auto', [FestResultsController::class, 'promoteAuto'])->name('results.promote-auto');
            Route::post('/{event}/results/qualifications/{qualification}/revoke', [FestResultsController::class, 'revokePromotion'])->name('results.qualifications.revoke');
            Route::get('/{event}/fees', [FestEventFeesController::class, 'index'])->name('fees.index');
            Route::get('/{event}/fees/ledger', [FestEventFeesController::class, 'ledger'])->name('fees.ledger');
            Route::get('/{event}/fees/export', [FestEventFeesController::class, 'exportPayments'])->name('fees.export');
            Route::get('/{event}/finance', [\App\Http\Controllers\SahodayaAdmin\FestFinanceController::class, 'index'])->name('finance.index');
            Route::post('/{event}/finance/issue-all', [\App\Http\Controllers\SahodayaAdmin\FestFinanceController::class, 'issueAll'])->name('finance.issue-all');
            Route::post('/{event}/finance/schools/{schoolId}', [\App\Http\Controllers\SahodayaAdmin\FestFinanceController::class, 'issueSchool'])->name('finance.issue-school');
            Route::get('/{event}/finance/invoices/{invoice}/pdf', [\App\Http\Controllers\SahodayaAdmin\FestFinanceController::class, 'pdf'])->name('finance.invoice-pdf');
            Route::get('/{event}/finance/invoices/{invoice}/demand-pdf', [\App\Http\Controllers\SahodayaAdmin\FestFinanceController::class, 'pdfDetailed'])->name('finance.invoice-demand-pdf');
            Route::get('/{event}/leaderboard', [\App\Http\Controllers\SahodayaAdmin\FestLeaderboardHubController::class, 'index'])->name('leaderboard.index');
            Route::get('/{event}/food-coupons', [\App\Http\Controllers\SahodayaAdmin\FestFoodCouponController::class, 'index'])->name('food-coupons.index');
            Route::post('/{event}/food-coupons/issue', [\App\Http\Controllers\SahodayaAdmin\FestFoodCouponController::class, 'issueFromCatering'])->name('food-coupons.issue');
            Route::post('/{event}/food-coupons/{coupon}/redeem', [\App\Http\Controllers\SahodayaAdmin\FestFoodCouponController::class, 'redeem'])->name('food-coupons.redeem');
            Route::get('/{event}/food-coupons/print', [\App\Http\Controllers\SahodayaAdmin\FestFoodCouponController::class, 'print'])->name('food-coupons.print');
            Route::get('/{event}/athletic-records', [\App\Http\Controllers\SahodayaAdmin\FestAthleticRecordController::class, 'index'])->name('athletic-records.index');
            Route::post('/{event}/athletic-records', [\App\Http\Controllers\SahodayaAdmin\FestAthleticRecordController::class, 'store'])->name('athletic-records.store');
            Route::delete('/{event}/athletic-records/{record}', [\App\Http\Controllers\SahodayaAdmin\FestAthleticRecordController::class, 'destroy'])->name('athletic-records.destroy');
            Route::post('/{event}/record-breaks/{break}/toggle-prize', [\App\Http\Controllers\SahodayaAdmin\FestAthleticRecordController::class, 'togglePrize'])->name('record-breaks.toggle-prize');
            Route::get('/{event}/record-breaks/{break}/certificate', [\App\Http\Controllers\SahodayaAdmin\FestAthleticRecordController::class, 'recordBreakCertificate'])->name('record-breaks.certificate');
            Route::post('/{event}/participation-policy', [\App\Http\Controllers\SahodayaAdmin\FestParticipationPolicyController::class, 'store'])->name('participation-policy.store');
            Route::post('/{event}/school-fees/{schoolEventFee}/approve', [\App\Http\Controllers\SahodayaAdmin\FestSchoolEventFeeController::class, 'approve'])->name('school-fees.approve');
            Route::post('/{event}/school-fees/{schoolEventFee}/reject', [\App\Http\Controllers\SahodayaAdmin\FestSchoolEventFeeController::class, 'reject'])->name('school-fees.reject');
            Route::get('/{event}/school-fees/{schoolEventFee}/proof', [\App\Http\Controllers\SahodayaAdmin\FestSchoolEventFeeController::class, 'proof'])->name('school-fees.proof');
            Route::post('/{event}/school-fees/{schoolEventFee}/recalculate', [\App\Http\Controllers\SahodayaAdmin\FestSchoolEventFeeController::class, 'recalculate'])->name('school-fees.recalculate');
            Route::get('/{event}/export/registrations', [FestExportController::class, 'registrations'])->name('export.registrations');
            Route::get('/{event}/export/results', [FestExportController::class, 'results'])->name('export.results');
            Route::get('/{event}/export/attendance', [FestExportController::class, 'attendance'])->name('export.attendance');
            Route::get('/{event}/export/fees', [FestExportController::class, 'fees'])->name('export.fees');
            Route::get('/{event}/chest-numbers', [FestChestNumberController::class, 'index'])->name('chest-numbers.index');
            Route::get('/{event}/chest-numbers/green-room', [FestChestNumberController::class, 'greenRoom'])->name('chest-numbers.green-room');
            Route::post('/{event}/chest-numbers/generate', [FestChestNumberController::class, 'generate'])->name('chest-numbers.generate');
            Route::post('/{event}/chest-numbers/assign-missing', [FestChestNumberController::class, 'assignMissing'])->name('chest-numbers.assign-missing');
            Route::post('/{event}/chest-numbers/assign-item-ids', [FestChestNumberController::class, 'assignItemRegIds'])->name('chest-numbers.assign-item-ids');
            Route::post('/{event}/chest-numbers/{participant}/clear', [FestChestNumberController::class, 'clearChest'])->name('chest-numbers.clear');
            Route::post('/{event}/chest-numbers/{participant}/reveal', [FestChestNumberController::class, 'revealChest'])->name('chest-numbers.reveal');
            Route::get('/{event}/chest-numbers/print', [FestChestNumberController::class, 'print'])->name('chest-numbers.print');
            Route::get('/{event}/chest-numbers/cards', [FestChestNumberController::class, 'cards'])->name('chest-numbers.cards');
            Route::get('/{event}/chest-numbers/csv', [FestChestNumberController::class, 'csv'])->name('chest-numbers.csv');
            Route::get('/{event}/id-cards', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'index'])->name('id-cards.index');
            Route::get('/{event}/id-cards/cards', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'cardsJson'])->name('id-cards.cards');
            Route::get('/{event}/id-cards/preview', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'preview'])->name('id-cards.preview');
            Route::get('/{event}/id-cards/pdf', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'pdf'])->name('id-cards.pdf');
            Route::get('/{event}/id-cards/pdf-all-items', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'pdfAllItems'])->name('id-cards.pdf-all-items');
            Route::get('/{event}/id-cards/pdf-all-heads', [\App\Http\Controllers\SahodayaAdmin\FestIdCardController::class, 'pdfAllHeads'])->name('id-cards.pdf-all-heads');
            Route::get('/{event}/settings/{tab?}', [FestEventSettingsController::class, 'settings'])
                ->where('tab', 'lifecycle|locks|venues|combo|grades|points|participation|eligibility|fees|registration|numbering|volunteers|records|clone')
                ->name('settings');
            Route::put('/{event}/settings', [FestEventSettingsController::class, 'updateSettings'])->name('settings.update');
            Route::put('/{event}/registration-settings', [FestEventSettingsController::class, 'updateRegistrationSettings'])->name('registration-settings.update');
            Route::put('/{event}/numbering-settings', [FestEventSettingsController::class, 'updateNumberingSettings'])->name('numbering-settings.update');
            Route::put('/{event}/item-numbering', [FestEventSettingsController::class, 'updateItemNumbering'])->name('item-numbering.update');
            Route::patch('/{event}/items/{item}/windows', [FestEventSettingsController::class, 'updateItemWindows'])->name('items.windows.update');
            Route::patch('/{event}/items/windows/bulk', [FestEventSettingsController::class, 'bulkUpdateItemWindows'])->name('items.windows.bulk-update');
            Route::post('/{event}/items/{item}/publish-results', [FestEventSettingsController::class, 'publishItemResults'])->name('items.publish-results');
            Route::get('/{event}/setup', [\App\Http\Controllers\SahodayaAdmin\FestSportsSetupController::class, 'index'])->name('setup.index');
            Route::post('/{event}/setup/sports', [\App\Http\Controllers\SahodayaAdmin\FestSportsSetupController::class, 'storeSport'])->name('setup.sports.store');
            Route::get('/{event}/competition', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadOpsController::class, 'index'])->name('competition.index');
            Route::get('/{event}/item-heads', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'index'])->name('item-heads.index');
            Route::post('/{event}/item-heads', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'store'])->name('item-heads.store');
            Route::post('/{event}/item-heads/sync', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'sync'])->name('item-heads.sync');
            Route::patch('/{event}/item-heads/{head}/windows', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'updateWindows'])->name('item-heads.windows.update');
            Route::patch('/{event}/item-heads/{head}/notifications', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'updateNotifications'])->name('item-heads.notifications.update');
            Route::delete('/{event}/item-heads/{head}', [\App\Http\Controllers\SahodayaAdmin\FestItemHeadController::class, 'destroy'])->name('item-heads.destroy');

            Route::get('/{event}/areas', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionAreaController::class, 'index'])->name('areas.index');
            Route::post('/{event}/areas', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionAreaController::class, 'store'])->name('areas.store');
            Route::put('/{event}/areas/{area}', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionAreaController::class, 'update'])->name('areas.update');
            Route::delete('/{event}/areas/{area}', [\App\Http\Controllers\SahodayaAdmin\FestCompetitionAreaController::class, 'destroy'])->name('areas.destroy');

            Route::get('/{event}/eligibility-rules', [\App\Http\Controllers\SahodayaAdmin\FestEligibilityRuleController::class, 'index'])->name('eligibility-rules.index');
            Route::post('/{event}/eligibility-rules', [\App\Http\Controllers\SahodayaAdmin\FestEligibilityRuleController::class, 'store'])->name('eligibility-rules.store');
            Route::put('/{event}/eligibility-rules/{eligibilityRule}', [\App\Http\Controllers\SahodayaAdmin\FestEligibilityRuleController::class, 'update'])->name('eligibility-rules.update');
            Route::delete('/{event}/eligibility-rules/{eligibilityRule}', [\App\Http\Controllers\SahodayaAdmin\FestEligibilityRuleController::class, 'destroy'])->name('eligibility-rules.destroy');
            Route::put('/{event}/eligibility-settings', [FestEventSettingsController::class, 'updateEligibilitySettings'])->name('eligibility-settings.update');
            Route::post('/{event}/lifecycle-settings', [FestEventSettingsController::class, 'updateLifecycleSettings'])->name('lifecycle-settings.update');
            Route::put('/{event}/fee-settings', [FestEventSettingsController::class, 'updateFeeSettings'])->name('fee-settings.update');
            Route::put('/{event}/ledger-account', [FestEventSettingsController::class, 'updateLedgerAccount'])->name('ledger-account.update');
            Route::patch('/{event}/items/{item}/fee', [FestEventSettingsController::class, 'updateItemFee'])->name('items.fee.update');
            Route::post('/{event}/venues', [FestEventSettingsController::class, 'storeVenue'])->name('venues.store');
            Route::delete('/{event}/venues/{venue}', [FestEventSettingsController::class, 'destroyVenue'])->name('venues.destroy');
            Route::post('/{event}/stages', [FestEventSettingsController::class, 'storeStage'])->name('stages.store');
            Route::delete('/{event}/stages/{stage}', [FestEventSettingsController::class, 'destroyStage'])->name('stages.destroy');
            Route::post('/{event}/combo-rules', [FestEventSettingsController::class, 'storeComboRule'])->name('combo-rules.store');
            Route::delete('/{event}/combo-rules/{comboRule}', [FestEventSettingsController::class, 'destroyComboRule'])->name('combo-rules.destroy');
            Route::post('/{event}/grade-configs', [FestEventSettingsController::class, 'storeGradeConfig'])->name('grade-configs.store');
            Route::delete('/{event}/grade-configs/{gradeConfig}', [FestEventSettingsController::class, 'destroyGradeConfig'])->name('grade-configs.destroy');
            Route::post('/{event}/point-rules', [FestEventSettingsController::class, 'storePointRule'])->name('point-rules.store');
            Route::delete('/{event}/point-rules/{pointRule}', [FestEventSettingsController::class, 'destroyPointRule'])->name('point-rules.destroy');
            Route::put('/{event}/rank-points', [FestEventSettingsController::class, 'updateRankPoints'])->name('rank-points.update');
            Route::post('/{event}/rank-points/seed-athletics', [FestEventSettingsController::class, 'seedRankPoints'])->name('rank-points.seed-athletics');
            Route::post('/{event}/volunteers', [FestEventSettingsController::class, 'storeVolunteer'])->name('volunteers.store');
            Route::delete('/{event}/volunteers/{volunteer}', [FestEventSettingsController::class, 'destroyVolunteer'])->name('volunteers.destroy');
            Route::post('/{event}/clone', [FestEventSettingsController::class, 'cloneEvent'])->name('clone');
            Route::post('/{event}/backfill-level-registrations', [FestEventSettingsController::class, 'backfillLevelRegistrations'])->name('backfill-level-registrations');
            Route::get('/certificates/search', [FestCertificateOpsController::class, 'search'])->name('certificates.search');
            Route::post('/{event}/certificates/participation', [FestCertificateOpsController::class, 'generateParticipation'])->name('certificates.participation');
            Route::post('/certificates/{certificate}/collect', [FestCertificateOpsController::class, 'collect'])->name('certificates.collect');
            Route::post('/certificates/bulk-collect', [FestCertificateOpsController::class, 'bulkCollect'])->name('certificates.bulk-collect');
            Route::get('/{event}/championship', [FestChampionshipController::class, 'index'])->name('championship.index');
            Route::post('/{event}/championship/recalculate', [FestChampionshipController::class, 'recalculate'])->name('championship.recalculate');
            Route::get('/{event}/marks/import', [FestMarksImportController::class, 'importForm'])->name('marks.import');
            Route::get('/{event}/marks/import-template', [FestMarksImportController::class, 'importTemplate'])->name('marks.import-template');
            Route::post('/{event}/marks/import', [FestMarksImportController::class, 'importStore'])->name('marks.import.store');
            Route::get('/{event}/certificates', [FestCertificateController::class, 'index'])->name('certificates.index');
            Route::post('/{event}/certificates/generate', [FestCertificateController::class, 'generate'])->name('certificates.generate');
            Route::get('/{event}/certificates/download-zip', [FestCertificateController::class, 'downloadZip'])->name('certificates.download-zip');
            Route::get('/{event}/houses', [FestHouseController::class, 'index'])->name('houses.index');
            Route::post('/{event}/houses', [FestHouseController::class, 'storeHouse'])->name('houses.store');
            Route::post('/{event}/houses/{house}/assign', [FestHouseController::class, 'assignSchool'])->name('houses.assign');
            Route::delete('/{event}/houses/{house}', [FestHouseController::class, 'destroyHouse'])->name('houses.destroy');
            Route::get('/{event}/appeals', [FestAppealController::class, 'index'])->name('appeals.index');
            Route::post('/{event}/appeals/{appeal}/resolve', [FestAppealController::class, 'resolve'])->name('appeals.resolve');
            Route::post('/{event}/appeals/{appeal}/mark-fee-paid', [FestAppealController::class, 'markFeePaid'])->name('appeals.mark-fee-paid');
            Route::post('/{event}/participants/{participant}/disqualify', [FestAppealController::class, 'disqualify'])->name('participants.disqualify');
            Route::post('/{event}/participants/{participant}/reinstate', [FestAppealController::class, 'reinstate'])->name('participants.reinstate');
            Route::get('/{event}/catering', [FestCateringController::class, 'index'])->name('catering.index');
            Route::put('/{event}/catering/{order}', [FestCateringController::class, 'updateStatus'])->name('catering.update');
            Route::get('/{event}/reports/downloads/{phase}', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'downloads'])
                ->where('phase', 'before|during|after')
                ->name('reports.downloads');
            Route::get('/{event}/reports', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'index'])->name('reports.index');
            Route::get('/{event}/reports/by-head', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'byHead'])->name('reports.by-head');
            Route::post('/{event}/reports/participation-rules', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'storeRule'])->name('reports.rules.store');
            Route::get('/{event}/reports/school-detailed', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'schoolDetailed'])->name('reports.school-detailed');
            Route::get('/{event}/reports/overall-ranking', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'overallRanking'])->name('reports.overall-ranking');
            Route::get('/{event}/reports/house-detailed', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'houseDetailed'])->name('reports.house-detailed');
            Route::get('/{event}/reports/participation-counts', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'participationCounts'])->name('reports.participation-counts');
            Route::get('/{event}/reports/mark-entry-status', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'markEntryStatus'])->name('reports.mark-entry-status');
            Route::get('/{event}/reports/schedule-clashes', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'scheduleClashes'])->name('reports.schedule-clashes');
            Route::get('/{event}/reports/item-schedule', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'itemSchedule'])->name('reports.item-schedule');
            Route::get('/{event}/reports/item-counts', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'itemCounts'])->name('reports.item-counts');
            Route::get('/{event}/reports/discipline-registration', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'disciplineRegistration'])->name('reports.discipline-registration');
            Route::get('/{event}/reports/head-wise-participants', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'headWiseParticipants'])->name('reports.head-wise-participants');
            Route::get('/{event}/reports/area-wise-participants', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'areaWiseParticipants'])->name('reports.area-wise-participants');
            Route::get('/{event}/reports/age-group-matrix', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'ageGroupMatrix'])->name('reports.age-group-matrix');
            Route::get('/{event}/reports/fee-collection', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'feeCollection'])->name('reports.fee-collection');
            Route::get('/{event}/reports/registration-register', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'registrationRegister'])->name('reports.registration-register');
            Route::get('/{event}/reports/registration-register/export', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'exportRegistrationRegister'])->name('reports.registration-register.export');
            Route::get('/{event}/reports/assignment-completeness', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'assignmentCompleteness'])->name('reports.assignment-completeness');
            Route::get('/{event}/reports/assignment-completeness/export', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'exportAssignmentCompleteness'])->name('reports.assignment-completeness.export');
            Route::get('/{event}/reports/numbering-register', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'numberingRegister'])->name('reports.numbering-register');
            Route::get('/{event}/reports/numbering-register/export', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'exportNumberingRegister'])->name('reports.numbering-register.export');
            Route::get('/{event}/reports/pending-approvals', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'pendingApprovals'])->name('reports.pending-approvals');
            Route::get('/{event}/reports/pending-approvals/export', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'exportPendingApprovals'])->name('reports.pending-approvals.export');
            Route::get('/{event}/reports/student-wise', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'studentWise'])->name('reports.student-wise');
            Route::get('/{event}/reports/item-wise', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'itemWise'])->name('reports.item-wise');
            Route::get('/{event}/reports/export/{exportType}', [\App\Http\Controllers\SahodayaAdmin\FestReportController::class, 'export'])->name('reports.export');
        });

        Route::prefix('display-screens')->name('display-screens.')->group(function () {
            Route::get('/', [ScreenSettingController::class, 'index'])->name('index');
            Route::post('/', [ScreenSettingController::class, 'store'])->name('store');
            Route::put('/{screen}', [ScreenSettingController::class, 'update'])->name('update');
            Route::delete('/{screen}', [ScreenSettingController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('mcq-series')->name('mcq-series.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'store'])->name('store');
            Route::get('/{series}', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'show'])->name('show');
            Route::post('/{series}/levels', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'storeLevel'])->name('levels.store');
            Route::post('/{series}/levels/bulk', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'storeLevelsBulk'])->name('levels.bulk');
            Route::post('/{series}/exams/{exam}/promotion/lock', [\App\Http\Controllers\SahodayaAdmin\McqExamSeriesController::class, 'lockPromotion'])->name('promotion.lock');
        });

        Route::prefix('mcq-exams')->name('mcq.')->group(function () {
            Route::get('/', [McqExamController::class, 'index'])->name('index');
            Route::post('/', [McqExamController::class, 'store'])->name('store');
            Route::get('/{exam}', [McqExamController::class, 'show'])->name('show');
            Route::put('/{exam}', [McqExamController::class, 'update'])->name('update');
            Route::post('/{exam}/question-paper', [McqExamController::class, 'uploadQuestionPaper'])->name('question-paper.upload');
            Route::delete('/{exam}/question-paper', [McqExamController::class, 'destroyQuestionPaper'])->name('question-paper.destroy');
            Route::post('/{exam}/registrations/{registration}/marks', [McqExamController::class, 'storeMark'])->name('marks.store');
            Route::post('/{exam}/registrations/{registration}/fee/approve', [McqExamController::class, 'approveFee'])->name('registrations.fee.approve');
            Route::post('/{exam}/registrations/{registration}/fee/reject', [McqExamController::class, 'rejectFee'])->name('registrations.fee.reject');
            Route::post('/{exam}/registrations/{registration}/approve', [McqExamController::class, 'approveRegistration'])->name('registrations.approve');
            Route::post('/{exam}/registrations/{registration}/reject', [McqExamController::class, 'rejectRegistration'])->name('registrations.reject');
            Route::get('/{exam}/registrations/{registration}/fee/proof', [McqExamController::class, 'feeProof'])->name('registrations.fee.proof');
            Route::post('/{exam}/publish-results', [McqExamController::class, 'publishResults'])->name('results.publish');
            Route::post('/{exam}/unpublish-results', [McqExamController::class, 'unpublishResults'])->name('results.unpublish');
            Route::get('/{exam}/leaderboard', [McqExamController::class, 'leaderboard'])->name('leaderboard');
            Route::get('/{exam}/leaderboard/export', [McqExamController::class, 'exportLeaderboard'])->name('leaderboard.export');
            Route::post('/{exam}/school-fees/{schoolFee}/approve', [McqExamController::class, 'approveSchoolFee'])->name('school-fees.approve');
            Route::get('/{exam}/payments', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'exam'])->name('payments');
            Route::get('/{exam}/ledger', [McqExamController::class, 'ledger'])->name('ledger');
            Route::put('/{exam}/ledger-account', [McqExamController::class, 'updateLedgerAccount'])->name('ledger-account.update');
            Route::post('/{exam}/payments/{schoolFee}/approve', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'approve'])->name('payments.approve');
            Route::post('/{exam}/payments/{schoolFee}/reject', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'reject'])->name('payments.reject');
            Route::get('/{exam}/reports', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'show'])->name('reports');
            Route::get('/{exam}/reports/registration/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportRegistration'])->name('reports.registration.export');
            Route::get('/{exam}/reports/fees/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportFees'])->name('reports.fees.export');
            Route::get('/{exam}/reports/attendance/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportAttendance'])->name('reports.attendance.export');
            Route::get('/{exam}/reports/toppers/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportToppers'])->name('reports.toppers.export');
            Route::get('/{exam}/reports/level2-qualifiers/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportLevel2Qualifiers'])->name('reports.level2-qualifiers.export');
            Route::get('/{exam}/reports/absent/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportAbsent'])->name('reports.absent.export');
            Route::get('/{exam}/reports/marks-pending/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportMarksPending'])->name('reports.marks-pending.export');
            Route::get('/{exam}/reports/fees-pending/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportPendingFees'])->name('reports.fees-pending.export');
            Route::get('/{exam}/reports/fees-rejected/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportRejectedFees'])->name('reports.fees-rejected.export');
            Route::get('/{exam}/reports/grade-bands/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportGradeBands'])->name('reports.grade-bands.export');
            Route::get('/{exam}/reports/session-status/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportSessionStatus'])->name('reports.session-status.export');
            Route::get('/{exam}/reports/result-analysis/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportResultAnalysis'])->name('reports.result-analysis.export');
            Route::get('/{exam}/reports/school-performance/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportSchoolPerformance'])->name('reports.school-performance.export');
            Route::get('/{exam}/reports/malpractice/export', [\App\Http\Controllers\SahodayaAdmin\McqReportController::class, 'exportMalpractice'])->name('reports.malpractice.export');
            Route::get('/{exam}/attendance', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'attendance'])->name('attendance');
            Route::post('/{exam}/attendance', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'storeAttendance'])->name('attendance.store');
            Route::post('/{exam}/attendance/import', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'importAttendance'])->name('attendance.import');
            Route::get('/{exam}/attendance/export', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'exportAttendance'])->name('attendance.export');
            Route::get('/{exam}/attendance/sheet.pdf', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'attendanceSheetPdf'])->name('attendance.sheet');
            Route::get('/{exam}/mark-sheet.pdf', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'markSheetPdf'])->name('mark-sheet');
            Route::get('/{exam}/result-sheet.pdf', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'resultSheetPdf'])->name('result-sheet');
            Route::get('/{exam}/registrations/{registration}/invoice', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'registrationInvoice'])->name('registrations.invoice');
            Route::get('/{exam}/attendance-corrections', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'attendanceCorrections'])->name('attendance-corrections');
            Route::post('/{exam}/attendance-corrections/{correctionRequest}/approve', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'approveAttendanceCorrection'])->name('attendance-corrections.approve');
            Route::post('/{exam}/attendance-corrections/{correctionRequest}/reject', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'rejectAttendanceCorrection'])->name('attendance-corrections.reject');
            Route::get('/{exam}/results', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'results'])->name('results');
            Route::get('/{exam}/hall-tickets', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'hallTickets'])->name('hall-tickets');
            Route::get('/{exam}/hall-tickets/preview', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'previewHallTicket'])->name('hall-tickets.preview');
            Route::post('/{exam}/hall-tickets/design', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'updateHallTicketDesign'])->name('hall-tickets.design');
            Route::post('/{exam}/hall-tickets/generate', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'generateHallTickets'])->name('hall-tickets.generate');
            Route::post('/{exam}/hall-tickets/halls', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'saveHalls'])->name('hall-tickets.halls');
            Route::post('/{exam}/hall-tickets/allocate-seats', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'allocateSeats'])->name('hall-tickets.allocate-seats');
            Route::get('/{exam}/hall-tickets/print-all', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'printAllHallTickets'])->name('hall-tickets.print-all');
            Route::get('/{exam}/staff', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'staff'])->name('staff');
            Route::post('/{exam}/staff', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'storeStaff'])->name('staff.store');
            Route::delete('/{exam}/staff/{staff}', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'destroyStaff'])->name('staff.destroy');
            Route::get('/{exam}/question-banks', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'questionBanks'])->name('question-banks');
            Route::post('/{exam}/question-banks', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'attachBank'])->name('question-banks.attach');
            Route::delete('/{exam}/question-banks/{bank}', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'detachBank'])->name('question-banks.detach');
            Route::get('/{exam}/session', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'sessionMonitor'])->name('session');
            Route::get('/{exam}/activity', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'activity'])->name('activity');
            Route::post('/{exam}/marks/bulk-import', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'bulkImportMarks'])->name('marks.bulk-import');
            Route::post('/{exam}/ranking/compute', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'computeRanking'])->name('ranking.compute');
            Route::get('/{exam}/certificates/preview', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'previewCertificate'])->name('certificates.preview');
            Route::post('/{exam}/certificates/generate', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'generateCertificates'])->name('certificates.generate');
            Route::get('/{exam}/registrations/{registration}/certificate', [\App\Http\Controllers\SahodayaAdmin\McqExamOpsController::class, 'printCertificate'])->name('certificates.print');
        });

        Route::prefix('training')->name('training.')->group(function () {
            Route::get('/', [TrainingProgramController::class, 'index'])->name('index');
            Route::post('/', [TrainingProgramController::class, 'store'])->name('store');

            Route::get('/resource-persons', [TrainingResourcePersonController::class, 'index'])->name('resource-persons.index');
            Route::post('/resource-persons', [TrainingResourcePersonController::class, 'store'])->name('resource-persons.store');
            Route::put('/resource-persons/{resourcePerson}', [TrainingResourcePersonController::class, 'update'])->name('resource-persons.update');
            Route::delete('/resource-persons/{resourcePerson}', [TrainingResourcePersonController::class, 'destroy'])->name('resource-persons.destroy');

            Route::post('/categories', [\App\Http\Controllers\SahodayaAdmin\TrainingCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [\App\Http\Controllers\SahodayaAdmin\TrainingCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [\App\Http\Controllers\SahodayaAdmin\TrainingCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/{program}', [TrainingProgramController::class, 'show'])->name('show');
            Route::put('/{program}', [TrainingProgramController::class, 'update'])->name('update');
            Route::get('/{program}/registrations', [TrainingProgramController::class, 'registrations'])->name('registrations');
            Route::get('/{program}/registrations/export', [TrainingProgramController::class, 'exportRegistrations'])->name('registrations.export');
            Route::get('/{program}/registrations/export-pdf', [TrainingProgramController::class, 'exportRegistrationsPdf'])->name('registrations.export-pdf');
            Route::get('/{program}/payments', [TrainingProgramController::class, 'payments'])->name('payments');
            Route::post('/{program}/registrations/{registration}/fee/record', [TrainingProgramController::class, 'recordPayment'])->name('registrations.fee.record');
            Route::post('/{program}/school-fees/{schoolFee}/approve', [TrainingProgramController::class, 'approveSchoolFee'])->name('school-fees.approve');
            Route::post('/{program}/school-fees/{schoolFee}/reject', [TrainingProgramController::class, 'rejectSchoolFee'])->name('school-fees.reject');
            Route::get('/{program}/school-fees/{schoolFee}/proof', [TrainingProgramController::class, 'schoolFeeProof'])->name('school-fees.proof');
            Route::get('/{program}/school-fees/{schoolFee}/invoice', [TrainingProgramController::class, 'schoolFeeInvoice'])->name('school-fees.invoice');
            Route::post('/{program}/sessions', [TrainingProgramController::class, 'storeSession'])->name('sessions.store');
            Route::put('/{program}/sessions/{session}', [TrainingProgramController::class, 'updateSession'])->name('sessions.update');
            Route::delete('/{program}/sessions/{session}', [TrainingProgramController::class, 'destroySession'])->name('sessions.destroy');
            Route::post('/{program}/resource-persons', [TrainingResourcePersonController::class, 'assign'])->name('resource-persons.assign');
            Route::put('/{program}/resource-persons/{resourcePerson}', [TrainingResourcePersonController::class, 'updateAssignment'])->name('resource-persons.assignment.update');
            Route::delete('/{program}/resource-persons/{resourcePerson}', [TrainingResourcePersonController::class, 'unassign'])->name('resource-persons.unassign');
            Route::post('/{program}/sessions/{session}/attendance', [TrainingProgramController::class, 'storeSessionAttendance'])->name('sessions.attendance');
            Route::post('/{program}/sessions/{session}/attendance/{registration}', [TrainingProgramController::class, 'updateAttendance'])->name('sessions.attendance.update');
            Route::post('/{program}/sessions/{session}/attendance/{registration}/review', [TrainingProgramController::class, 'reviewAttendanceCorrection'])->name('sessions.attendance.review');
            Route::get('/{program}/attendance', [TrainingProgramController::class, 'attendance'])->name('attendance');
            Route::get('/{program}/attendance/sheet', [TrainingProgramController::class, 'attendanceSheet'])->name('attendance.sheet');
            Route::get('/{program}/attendance/sheet/pdf', [TrainingProgramController::class, 'exportAttendanceSheetPdf'])->name('attendance.sheet.pdf');
            Route::get('/{program}/attendance/report', [TrainingProgramController::class, 'attendanceReport'])->name('attendance.report');
            Route::get('/{program}/attendance/report/pdf', [TrainingProgramController::class, 'exportAttendanceReportPdf'])->name('attendance.report.pdf');
            Route::get('/{program}/attendance/export', [TrainingProgramController::class, 'exportAttendance'])->name('attendance.export');
            Route::get('/{program}/attendance/export-pdf', [TrainingProgramController::class, 'exportAttendanceSheetPdf'])->name('attendance.export-pdf');
            Route::post('/{program}/registrations/{registration}/confirm', [TrainingProgramController::class, 'confirmRegistration'])->name('registrations.confirm');
            Route::post('/{program}/registrations/{registration}/cancel', [TrainingProgramController::class, 'cancelRegistration'])->name('registrations.cancel');
            Route::post('/{program}/registrations/{registration}/fee/approve', [TrainingProgramController::class, 'approveFee'])->name('registrations.fee.approve');
            Route::post('/{program}/registrations/{registration}/fee/reject', [TrainingProgramController::class, 'rejectFee'])->name('registrations.fee.reject');
            Route::get('/{program}/registrations/{registration}/fee/proof', [TrainingProgramController::class, 'feeProof'])->name('registrations.fee.proof');
            Route::get('/{program}/registrations/{registration}/invoice', [TrainingProgramController::class, 'registrationInvoice'])->name('registrations.invoice');
            Route::get('/{program}/registrations/{registration}/id-card', [TrainingProgramController::class, 'registrationIdCard'])->name('registrations.id-card');
            Route::get('/{program}/certificate/preview', [TrainingProgramController::class, 'previewCertificate'])->name('certificate.preview');
            Route::get('/{program}/registrations/{registration}/certificate/preview', [TrainingProgramController::class, 'previewRegistrationCertificate'])->name('registrations.certificate.preview');
            Route::post('/{program}/registrations/{registration}/certificate', [TrainingProgramController::class, 'issueCertificate'])->name('registrations.certificate');
            Route::get('/{program}/registrations/{registration}/certificate/print', [TrainingProgramController::class, 'printCertificate'])->name('registrations.certificate.print');
            Route::get('/{program}/certificates/export', [TrainingProgramController::class, 'exportCertificatesZip'])->name('certificates.export');
            Route::get('/{program}/ledger', [TrainingProgramController::class, 'ledger'])->name('ledger');
            Route::put('/{program}/ledger-account', [TrainingProgramController::class, 'updateLedgerAccount'])->name('ledger-account.update');
            Route::get('/{program}/qr/{kind}/{format}', [TrainingProgramController::class, 'downloadQr'])->name('qr.download');
            Route::post('/{program}/qr/regenerate', [TrainingProgramController::class, 'regenerateQr'])->name('qr.regenerate');
            Route::get('/{program}/qr-reports', [TrainingProgramController::class, 'qrReports'])->name('qr-reports');
            Route::get('/{program}/qr-reports/export', [TrainingProgramController::class, 'exportQrRegistrations'])->name('qr-reports.export');
            Route::get('/{program}/qr-teachers', [TrainingProgramController::class, 'qrTeachers'])->name('qr-teachers');
            Route::get('/{program}/feedback', [TrainingProgramController::class, 'feedback'])->name('feedback');
            Route::post('/{program}/feedback/{feedback}/review', [TrainingProgramController::class, 'markFeedbackReviewed'])->name('feedback.review');
            Route::post('/{program}/pending-schools/{pendingSchool}/link', [TrainingProgramController::class, 'linkPendingSchool'])->name('pending-schools.link');
            Route::post('/{program}/pending-schools/{pendingSchool}/reject', [TrainingProgramController::class, 'rejectPendingSchool'])->name('pending-schools.reject');
            Route::get('/{program}/sessions/{session}/qr/{format}', [TrainingProgramController::class, 'downloadSessionAttendanceQr'])->name('sessions.qr');
        });

        Route::prefix('ledger')->name('ledger.')->group(function () {
            Route::get('/', [LedgerController::class, 'index'])->name('index');
            Route::get('/reports', [LedgerController::class, 'reports'])->name('reports');
            Route::get('/opening-balances', [LedgerController::class, 'openingBalances'])->name('opening-balances');
            Route::post('/opening-balances', [LedgerController::class, 'storeOpeningBalance'])->name('opening-balances.store');
            Route::delete('/opening-balances/{openingBalance}', [LedgerController::class, 'destroyOpeningBalance'])->name('opening-balances.destroy');
            Route::patch('/heads/{head}', [LedgerController::class, 'updateAccountHead'])->name('heads.update');
            Route::get('/export', [LedgerController::class, 'export'])->name('export');
            Route::post('/heads', [LedgerController::class, 'storeHead'])->name('heads.store');
            Route::delete('/heads/{head}', [LedgerController::class, 'destroyHead'])->name('heads.destroy');
            Route::post('/transactions', [LedgerController::class, 'storeTransaction'])->name('transactions.store');
            Route::post('/expenses', [LedgerController::class, 'storeExpense'])->name('expenses.store');
        });

        Route::prefix('certificate-templates')->name('certificate-templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\CertificateTemplateController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SahodayaAdmin\CertificateTemplateController::class, 'store'])->name('store');
            Route::get('/{template}/preview', [\App\Http\Controllers\SahodayaAdmin\CertificateTemplateController::class, 'preview'])->name('preview');
            Route::put('/{template}', [\App\Http\Controllers\SahodayaAdmin\CertificateTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [\App\Http\Controllers\SahodayaAdmin\CertificateTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('state-remittances')->name('state-remittances.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SahodayaAdmin\StateRemittanceController::class, 'index'])->name('index');
            Route::post('/{remittance}/proof', [\App\Http\Controllers\SahodayaAdmin\StateRemittanceController::class, 'uploadProof'])->name('proof');
        });

        Route::post('/membership/logo', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'uploadLogo'])->name('membership.logo');
        Route::put('/membership/application-form', [\App\Http\Controllers\SahodayaAdmin\MembershipSettingsController::class, 'updateApplicationForm'])->name('membership.application-form.update');
    });

// Auth routes
Route::middleware('web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/school-login', [AuthController::class, 'showSchoolLogin'])->name('school.login');
    Route::get('/portal/login', [AuthController::class, 'showPortalLogin'])->name('portal.login');
    Route::get('/portal/s/{schoolCode}/login', [\App\Http\Controllers\Portal\PortalLoginController::class, 'school'])->name('portal.login.school');
    Route::get('/portal/forgot-password', [AuthController::class, 'showForgotPassword'])->name('portal.password.request');
    Route::post('/portal/forgot-password', [AuthController::class, 'sendResetLink'])->name('portal.password.email');
    Route::get('/portal/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('portal.password.reset');
    Route::post('/portal/reset-password', [AuthController::class, 'resetPassword'])->name('portal.password.update');
    // Laravel's default password reset notification expects these route names.
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/exports/{exportJob}/download', \App\Http\Controllers\ExportJobDownloadController::class)->name('exports.download');
        Route::get('/change-password', [\App\Http\Controllers\ChangePasswordController::class, 'show'])->name('password.change');
        Route::post('/change-password', [\App\Http\Controllers\ChangePasswordController::class, 'store'])->name('password.change.store');
        Route::get('/portal/welcome', [\App\Http\Controllers\PortalWelcomeController::class, 'show'])->name('portal.welcome');
        Route::post('/portal/welcome', [\App\Http\Controllers\PortalWelcomeController::class, 'store'])->name('portal.welcome.store');
        Route::get('/email/verify', [AuthController::class, 'verifyNotice'])->name('verification.notice');
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->name('verification.send');
    });

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
});

// ── Student & Teacher Portals (Phase 10) ─────────────────────────────────────
Route::prefix('portal/fest-ops/{tenantId}')
    ->name('portal.fest-ops.')
    ->middleware(['web', 'auth', 'password.change', 'fest.event.ops', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'index'])->name('dashboard');
        Route::get('/events/{event}', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'event'])->name('event');
        Route::get('/events/{event}/coordinator', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'coordinator'])->name('coordinator');
        Route::get('/events/{event}/registrations', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'registrations'])->name('registrations');
        Route::post('/events/{event}/registrations/bulk-approve', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'bulkApproveRegistrations'])->name('registrations.bulk-approve');
        Route::post('/events/{event}/registrations/bulk-reject', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'bulkRejectRegistrations'])->name('registrations.bulk-reject');
        Route::post('/events/{event}/registrations/{registration}/approve', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'approveRegistration'])->name('registrations.approve');
        Route::post('/events/{event}/registrations/{registration}/reject', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'rejectRegistration'])->name('registrations.reject');
        Route::post('/events/{event}/registrations/{registration}/cancel', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'cancelRegistration'])->name('registrations.cancel');
        Route::post('/events/{event}/registrations/{registration}/substitute/{performer}/{standby}', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'substituteRegistration'])->name('registrations.substitute');
        Route::get('/events/{event}/participants/search', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'participantSearch'])->name('participants.search');
        Route::get('/events/{event}/marks', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'marks'])->name('marks');
        Route::post('/events/{event}/marks', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'storeMark'])->name('marks.store');
        Route::post('/events/{event}/items/{item}/auto-rank', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'autoRankItem'])->name('items.auto-rank');
        Route::get('/events/{event}/admit-card', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'admitCard'])->name('admit-card');
        Route::get('/events/{event}/appeals', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'appeals'])->name('appeals');
        Route::post('/events/{event}/appeals/{appeal}/resolve', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'resolveAppeal'])->name('appeals.resolve');
        Route::get('/events/{event}/certificates', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'certificates'])->name('certificates');
        Route::get('/events/{event}/stage', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'stage'])->name('stage');
        Route::post('/events/{event}/stage/reorder', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'reorderStage'])->name('stage.reorder');
        Route::post('/events/{event}/stage/{schedule}/called', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'markStageCalled'])->name('stage.called');
        Route::get('/events/{event}/attendance', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'attendance'])->name('attendance');
        Route::post('/events/{event}/attendance', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'storeAttendance'])->name('attendance.store');
        Route::get('/events/{event}/kitchen', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'kitchen'])->name('kitchen');
        Route::post('/events/{event}/kitchen/{order}', [\App\Http\Controllers\Portal\FestEventOpsController::class, 'updateOrderStatus'])->name('kitchen.update');
        Route::get('/gate-check', [\App\Http\Controllers\Portal\FestGateController::class, 'index'])->name('gate-check');
        Route::post('/events/{event}/gate-check', [\App\Http\Controllers\Portal\FestGateController::class, 'verify'])->name('gate-check.verify');
        Route::post('/events/{event}/gate-check/json', [\App\Http\Controllers\Portal\FestGateController::class, 'verifyJson'])->name('gate-check.verify-json');
    });

Route::prefix('portal/judge/{tenantId}')
    ->name('portal.judge.')
    ->middleware(['web', 'auth', 'password.change', 'judge.portal', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [JudgeDashboardController::class, 'index'])->name('dashboard');
        Route::get('/events/{event}/marks', [JudgeDashboardController::class, 'marks'])->name('marks');
        Route::post('/events/{event}/marks', [JudgeDashboardController::class, 'storeMark'])->name('marks.store');
    });

Route::prefix('portal/fest-coordinator/{tenantId}')
    ->name('portal.fest-coordinator.')
    ->middleware(['web', 'auth', 'password.change', 'fest.mark.coordinator', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\FestMarkCoordinatorController::class, 'index'])->name('dashboard');
        Route::get('/events/{event}/marks', [\App\Http\Controllers\Portal\FestMarkCoordinatorController::class, 'marks'])->name('marks');
        Route::post('/events/{event}/marks', [\App\Http\Controllers\Portal\FestMarkCoordinatorController::class, 'storeMark'])->name('marks.store');
        Route::post('/events/{event}/attendance', [\App\Http\Controllers\Portal\FestMarkCoordinatorController::class, 'storeAttendance'])->name('attendance.store');
        Route::post('/events/{event}/items/{item}/auto-rank', [\App\Http\Controllers\Portal\FestMarkCoordinatorController::class, 'autoRankItem'])->name('items.auto-rank');
    });

Route::prefix('portal/teacher/{tenantId}')
    ->name('portal.teacher.')
    ->middleware(['web', 'auth', 'password.change', 'teacher.portal', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
        Route::get('/training', [TeacherDashboardController::class, 'trainingPage'])->name('training');
        Route::post('/training/programs/{program}/register', [\App\Http\Controllers\Portal\TeacherTrainingRegistrationController::class, 'register'])->name('training.register');
        Route::post('/training/registrations/{registration}/payment', [\App\Http\Controllers\Portal\TeacherTrainingRegistrationController::class, 'uploadPayment'])->name('training.payment');
        Route::post('/training/registrations/{registration}/feedback', [\App\Http\Controllers\Portal\TeacherTrainingRegistrationController::class, 'submitFeedback'])->name('training.feedback');
        Route::get('/fest', [TeacherDashboardController::class, 'festPage'])->name('fest');
        Route::get('/fest/schedule', [TeacherDashboardController::class, 'festSchedulePage'])->name('fest.schedule');
        Route::get('/results', [TeacherDashboardController::class, 'resultsPage'])->name('results');
        Route::get('/certificates', [TeacherDashboardController::class, 'certificatesPage'])->name('certificates');
        Route::get('/fest/{event}/admit-card', [TeacherDashboardController::class, 'admitCard'])->name('fest.admit-card');
        Route::post('/fest/{event}/appeals', [\App\Http\Controllers\Portal\PortalFestAppealController::class, 'storeTeacher'])->name('fest.appeals.store');
        Route::get('/training/{registration}/certificate', [TeacherDashboardController::class, 'trainingCertificate'])->name('training.certificate');
        Route::get('/question-banks', [\App\Http\Controllers\Portal\TeacherMcqController::class, 'banks'])->name('question-banks');
        Route::post('/question-banks', [\App\Http\Controllers\Portal\TeacherMcqController::class, 'storeBank'])->name('question-banks.store');
        Route::get('/question-banks/{bank}', [\App\Http\Controllers\Portal\TeacherMcqController::class, 'showBank'])->name('question-banks.show');
        Route::post('/question-banks/{bank}/questions', [\App\Http\Controllers\Portal\TeacherMcqController::class, 'storeQuestion'])->name('questions.store');
        Route::delete('/question-banks/{bank}/questions/{question}', [\App\Http\Controllers\Portal\TeacherMcqController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::get('/exams', [\App\Http\Controllers\Portal\TeacherMcqRegistrationController::class, 'index'])->name('exams');
        Route::post('/exams/{exam}/register', [\App\Http\Controllers\Portal\TeacherMcqRegistrationController::class, 'register'])->name('exams.register');
        Route::get('/profile', [\App\Http\Controllers\Portal\TeacherProfileController::class, 'edit'])->name('profile');
        Route::get('/photo', [\App\Http\Controllers\Portal\TeacherProfileController::class, 'photo'])->name('photo');
        Route::put('/profile', [\App\Http\Controllers\Portal\TeacherProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [\App\Http\Controllers\Portal\TeacherProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/change-request', [\App\Http\Controllers\Portal\ProfileChangeRequestController::class, 'store'])->name('profile.change-request');
    });

Route::prefix('portal/exam/{tenantId}')
    ->name('portal.exam.')
    ->middleware(['web', 'auth', 'password.change', 'exam.portal', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\ExamOpsController::class, 'index'])->name('dashboard');
        Route::get('/exams/{exam}/attendance', [\App\Http\Controllers\Portal\ExamOpsController::class, 'attendance'])->name('attendance');
        Route::post('/exams/{exam}/attendance', [\App\Http\Controllers\Portal\ExamOpsController::class, 'storeAttendance'])->name('attendance.store');
        Route::post('/exams/{exam}/attendance/import', [\App\Http\Controllers\Portal\ExamOpsController::class, 'importAttendance'])->name('attendance.import');
        Route::get('/exams/{exam}/marks', [\App\Http\Controllers\Portal\ExamOpsController::class, 'marks'])->name('marks');
        Route::post('/exams/{exam}/registrations/{registration}/marks', [\App\Http\Controllers\Portal\ExamOpsController::class, 'storeMark'])->name('marks.store');
        Route::get('/exams/{exam}/supervision', [\App\Http\Controllers\Portal\ExamOpsController::class, 'supervision'])->name('supervision');
    });

Route::prefix('portal/house-admin/{tenantId}')
    ->name('portal.house.')
    ->middleware(['web', 'auth', 'password.change', 'house.admin', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\HouseAdminController::class, 'index'])->name('dashboard');
        Route::get('/students', [\App\Http\Controllers\Portal\HouseAdminController::class, 'students'])->name('students');
        Route::get('/registrations', [\App\Http\Controllers\Portal\HouseAdminController::class, 'registrations'])->name('registrations');
        Route::get('/ranking', [\App\Http\Controllers\Portal\HouseAdminController::class, 'ranking'])->name('ranking');
    });

Route::prefix('portal/group/{tenantId}')
    ->name('portal.group.')
    ->middleware(['web', 'auth', 'password.change', 'group.admin', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\GroupAdminController::class, 'index'])->name('dashboard');
        Route::get('/students', [\App\Http\Controllers\Portal\GroupAdminController::class, 'students'])->name('students');
        Route::get('/fest/registrations', [\App\Http\Controllers\Portal\GroupAdminController::class, 'festRegistrations'])->name('fest.registrations');
        Route::get('/fest/schedule', [\App\Http\Controllers\Portal\GroupAdminController::class, 'festSchedule'])->name('fest.schedule');
        Route::get('/fest/clashes', [\App\Http\Controllers\Portal\GroupAdminController::class, 'festClashes'])->name('fest.clashes');
        Route::get('/fest/admit-cards', [\App\Http\Controllers\Portal\GroupAdminController::class, 'festAdmitCards'])->name('fest.admit-cards');
        Route::get('/fest/{event}/admit-cards/download', [\App\Http\Controllers\Portal\GroupAdminController::class, 'downloadAdmitCards'])->name('fest.admit-cards.download');
    });

Route::prefix('portal/student/{tenantId}')
    ->name('portal.student.')
    ->middleware(['web', 'auth', 'password.change', 'student.portal', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->group(function () {
        Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/mcq', [StudentDashboardController::class, 'mcqHub'])->name('mcq');
        Route::get('/fest/schedule', [StudentDashboardController::class, 'festSchedule'])->name('fest.schedule');
        Route::get('/results', [StudentDashboardController::class, 'festResultsPage'])->name('results');
        Route::get('/certificates', [StudentDashboardController::class, 'festCertificates'])->name('certificates');
        Route::get('/sports-results', [StudentDashboardController::class, 'sportsResults'])->name('sports-results');
        Route::get('/fest/{event}/admit-card', [StudentDashboardController::class, 'admitCard'])->name('fest.admit-card');
        Route::get('/fest/{event}/id-card', [StudentDashboardController::class, 'festIdCard'])->name('fest.id-card');
        Route::post('/fest/{event}/appeals', [\App\Http\Controllers\Portal\PortalFestAppealController::class, 'storeStudent'])->name('fest.appeals.store');
        Route::get('/mcq/{registration}/hall-ticket', [\App\Http\Controllers\Portal\StudentMcqController::class, 'hallTicket'])->name('mcq.hall-ticket');
        Route::get('/mcq/{registration}/certificate', [\App\Http\Controllers\Portal\StudentMcqController::class, 'certificate'])->name('mcq.certificate');
        Route::get('/mcq/{registration}/invoice', [\App\Http\Controllers\Portal\StudentMcqController::class, 'invoice'])->name('mcq.invoice');
        Route::get('/mcq/{registration}/exam', [\App\Http\Controllers\Portal\StudentMcqController::class, 'showExam'])->name('mcq.exam');
        Route::post('/mcq/{registration}/start', [\App\Http\Controllers\Portal\StudentMcqController::class, 'startExam'])->name('mcq.start');
        Route::post('/mcq/{registration}/save-answers', [\App\Http\Controllers\Portal\StudentMcqController::class, 'saveAnswer'])->name('mcq.save-answers');
        Route::post('/mcq/{registration}/submit', [\App\Http\Controllers\Portal\StudentMcqController::class, 'submitExam'])->name('mcq.submit');
        Route::get('/profile', [\App\Http\Controllers\Portal\StudentProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [\App\Http\Controllers\Portal\StudentProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [\App\Http\Controllers\Portal\StudentProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/fest-registrations', [\App\Http\Controllers\Portal\FestStudentRegistrationController::class, 'index'])->name('fest-registrations');
        Route::get('/fest/{event}/eligible-items', [\App\Http\Controllers\Portal\FestStudentRegistrationController::class, 'eligibleItems'])->name('fest.eligible-items');
        Route::post('/fest/{event}/register', [\App\Http\Controllers\Portal\FestStudentRegistrationController::class, 'registerEvent'])->name('fest.register-event');
        Route::post('/fest/{event}/items/{item}/register', [\App\Http\Controllers\Portal\FestStudentRegistrationController::class, 'registerItem'])->name('fest.register-item');
    });

Route::get('/certificates/verify/{uuid}', [PublicCertificateController::class, 'verify'])
    ->middleware(['web', 'throttle:60,1'])
    ->name('certificates.verify');

Route::get('/certificates/print/{uuid}', [PublicCertificateController::class, 'print'])
    ->middleware(['web', 'throttle:60,1'])
    ->name('certificates.print');

Route::get('/verify/{uuid}', [PublicCertificateController::class, 'verify'])
    ->middleware(['web', 'throttle:60,1'])
    ->name('verify');

// ── Live display screens (Phase 20) ──────────────────────────────────────────
Route::get('/display/{tenantId}/{slug}', [DisplayScreenController::class, 'show'])
    ->middleware(['web', \App\Http\Middleware\InitializeTenancyByRouteTenant::class])
    ->name('display.show');
