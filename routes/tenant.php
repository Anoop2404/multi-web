<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AdmissionEnquiryController;
use App\Http\Controllers\Public\RegistrationLandingController;
use App\Http\Controllers\Public\FestPortalController;
use App\Http\Controllers\Public\McqArchiveController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\GalleryAlbumController;
use App\Http\Controllers\Public\NewsArticleController;
use App\Http\Controllers\Public\SchoolApplicationController;
use App\Http\Controllers\Public\SahodayaCmsPageController;
use App\Http\Controllers\Public\SeoController;
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

    // Portal landing (register + login options; always available)
    Route::get('/portal', RegistrationLandingController::class)->name('tenant.portal');

    // School membership application (always available on Sahodaya tenants)
    Route::get('/school-register', [SchoolApplicationController::class, 'create'])->name('school-register.create');
    Route::post('/school-register', [SchoolApplicationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('school-register.store');

    // Teacher training QR registration + attendance (Sahodaya public)
    Route::prefix('training')->name('tenant.training.')->group(function () {
        Route::get('/register/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'show'])->name('register.show');
        Route::get('/register/{token}/schools', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'searchSchools'])->name('register.schools');
        Route::post('/register/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('register.store');
        Route::get('/register/{token}/success', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'success'])->name('register.success');

        Route::get('/attendance/program/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'attendanceProgram'])->name('attendance.program');
        Route::post('/attendance/program/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'storeAttendance'])
            ->middleware('throttle:30,1')
            ->name('attendance.program.store');
        Route::get('/attendance/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'attendanceSession'])->name('attendance.show');
        Route::post('/attendance/{token}', [\App\Http\Controllers\Public\TrainingQrRegistrationController::class, 'storeAttendance'])
            ->middleware('throttle:30,1')
            ->name('attendance.store');
    });

    // Public festival portal (always available on Sahodaya tenants)
    Route::prefix('fest')->name('tenant.fest.')->group(function () {
        Route::get('/', [FestPortalController::class, 'index'])->name('index');
        Route::get('/{event}', [FestPortalController::class, 'show'])->name('show');
        Route::get('/{event}/schedule', [FestPortalController::class, 'schedule'])->name('schedule');
        Route::get('/{event}/results', [FestPortalController::class, 'results'])->name('results');
        Route::get('/{event}/items/{item}', [FestPortalController::class, 'itemSchedule'])->name('item-schedule');
        Route::get('/{event}/items/{item}/results', [FestPortalController::class, 'itemResults'])->name('item-results');
        Route::get('/{event}/items/{item}/results.pdf', [FestPortalController::class, 'itemResultsPdf'])->name('item-results.pdf');
        Route::get('/{event}/items/{item}/winners/{mark}/poster.svg', [FestPortalController::class, 'winnerPoster'])->name('winner-poster');
        Route::get('/{event}/scoreboard', [FestPortalController::class, 'scoreboard'])->name('scoreboard');
        Route::get('/{event}/manual', [FestPortalController::class, 'manual'])->name('manual');
        Route::get('/{event}/live', [FestPortalController::class, 'live'])->name('live');
        Route::get('/{event}/live/data', [FestPortalController::class, 'liveData'])->name('live.data');
        Route::get('/{event}/records', [FestPortalController::class, 'records'])->name('records');
        Route::get('/{event}/search', [FestPortalController::class, 'search'])->name('search');
        Route::get('/{event}/participant/{ref}', [FestPortalController::class, 'participant'])->name('participant');
    });

    Route::prefix('mcq')->name('tenant.mcq.')->group(function () {
        Route::get('/papers', [McqArchiveController::class, 'index'])->name('archive');
        Route::get('/papers/{exam}/download', [McqArchiveController::class, 'download'])->name('archive.download');
    });

    Route::prefix('academic-results')->name('tenant.academic-results.')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [\App\Http\Controllers\Public\AcademicResultsPortalController::class, 'index'])->name('index');
        Route::get('/merit-list.pdf', [\App\Http\Controllers\Public\AcademicResultsPortalController::class, 'meritListPdf'])->name('merit-list');
    });

    // Public website pages (require global + tenant public-site setting)
    Route::middleware(['website.enabled', 'public.website.enabled'])->group(function () {
        Route::get('/news', [NewsArticleController::class, 'index'])->name('tenant.news.index');
        Route::get('/news/{slug}', [NewsArticleController::class, 'show'])->name('tenant.news.show');
        Route::get('/events', [EventController::class, 'index'])->name('tenant.events.index');
        Route::get('/events/{slug}', [EventController::class, 'show'])->name('tenant.events.show');

        // CKSC-style CMS pages (must be registered before /gallery/{slug})
        Route::get('/about', fn () => app(SahodayaCmsPageController::class)->show('about'))->name('tenant.sahodaya.about');
        Route::get('/executive', fn () => app(SahodayaCmsPageController::class)->show('executive'))->name('tenant.sahodaya.executive');
        Route::get('/contact', fn () => app(SahodayaCmsPageController::class)->show('contact'))->name('tenant.sahodaya.contact');
        Route::get('/contactus', fn () => app(SahodayaCmsPageController::class)->show('contact'));
        Route::get('/downloads', fn () => app(SahodayaCmsPageController::class)->show('downloads'))->name('tenant.sahodaya.downloads');
        Route::get('/download', fn () => app(SahodayaCmsPageController::class)->show('downloads'));
        Route::get('/gallery/function', fn () => app(SahodayaCmsPageController::class)->show('gallery/function'))->name('tenant.sahodaya.gallery.function');
        Route::get('/gallery/programme', fn () => app(SahodayaCmsPageController::class)->show('gallery/programme'))->name('tenant.sahodaya.gallery.programme');
        Route::get('/gallery/sahodya', fn () => app(SahodayaCmsPageController::class)->show('gallery/sahodya'))->name('tenant.sahodaya.gallery.sahodya');
        Route::get('/moa/structure', fn () => app(SahodayaCmsPageController::class)->show('moa/structure'))->name('tenant.sahodaya.moa.structure');
        Route::get('/moa/rules', fn () => app(SahodayaCmsPageController::class)->show('moa/rules'))->name('tenant.sahodaya.moa.rules');
        Route::get('/moa/meetings', fn () => app(SahodayaCmsPageController::class)->show('moa/meetings'))->name('tenant.sahodaya.moa.meetings');
        Route::get('/moa/authority', fn () => app(SahodayaCmsPageController::class)->show('moa/authority'))->name('tenant.sahodaya.moa.authority');
        Route::get('/moa/activities', fn () => app(SahodayaCmsPageController::class)->show('moa/activities'))->name('tenant.sahodaya.moa.activities');
        Route::get('/moa/election', fn () => app(SahodayaCmsPageController::class)->show('moa/election'))->name('tenant.sahodaya.moa.election');

        Route::get('/gallery/{slug}', [GalleryAlbumController::class, 'show'])->name('tenant.gallery.show');

        Route::post('/admission-enquiry', [AdmissionEnquiryController::class, 'store'])->name('admission-enquiry.store');

        Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
        Route::get('/robots.txt', [SeoController::class, 'robots']);
    });
});
