<?php

use App\Http\Controllers\SahodayaAdmin\AthleticRecordsDashboardController;
use App\Http\Controllers\SahodayaAdmin\FestCatalogController;
use App\Http\Controllers\SahodayaAdmin\FestEventController;
use App\Http\Controllers\SahodayaAdmin\EnglishFestProgramController;
use App\Http\Controllers\SahodayaAdmin\KidsFestProgramController;
use App\Http\Controllers\SahodayaAdmin\KalotsavProgramController;
use App\Http\Controllers\SahodayaAdmin\McqDashboardController;
use App\Http\Controllers\SahodayaAdmin\SportsAgeGroupController;
use App\Http\Controllers\SahodayaAdmin\ScienceFestProgramController;
use App\Http\Controllers\SahodayaAdmin\SportsProgramController;
use App\Http\Controllers\SahodayaAdmin\TeacherFestProgramController;
use App\Support\FestCatalogSections;
use Illuminate\Support\Facades\Route;

$sahodayaFestPrograms = [
    ['prefix' => 'kalotsav', 'slug' => 'kalotsav', 'controller' => KalotsavProgramController::class],
    ['prefix' => 'sports', 'slug' => 'sports-meet', 'controller' => SportsProgramController::class],
    ['prefix' => 'kids-fest', 'slug' => 'kids-fest', 'controller' => KidsFestProgramController::class],
    ['prefix' => 'teacher-fest', 'slug' => 'teacher-fest', 'controller' => TeacherFestProgramController::class],
    ['prefix' => 'english-fest', 'slug' => 'english-fest', 'controller' => EnglishFestProgramController::class],
    ['prefix' => 'science-fest', 'slug' => 'science-fest', 'controller' => ScienceFestProgramController::class],
];

$catalogEventTypes = [
    'kalotsav'     => 'kalolsavam',
    'sports-meet'  => 'sports',
    'kids-fest'    => 'kids_fest',
    'teacher-fest' => 'teacher_fest',
    'english-fest' => 'english_fest',
    'science-fest' => 'science_fest',
];

foreach ($sahodayaFestPrograms as $cfg) {
    $prefix = $cfg['prefix'];
    $slug = $cfg['slug'];
    $controller = $cfg['controller'];
    $eventType = $catalogEventTypes[$slug] ?? null;

    Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller, $slug, $prefix, $eventType) {
        Route::get('/', [$controller, 'dashboard'])->name('dashboard');

        if ($prefix === 'kalotsav') {
            Route::get('/school-rounds', [KalotsavProgramController::class, 'schoolRounds'])->name('school-rounds');
        }

        if ($prefix === 'sports') {
            Route::get('/records', [AthleticRecordsDashboardController::class, 'index'])->name('records');
            Route::get('/championship', [SportsProgramController::class, 'championship'])->name('championship');
            Route::get('/results', [SportsProgramController::class, 'results'])->name('results');
            Route::get('/rankings', [SportsProgramController::class, 'rankings'])->name('rankings');

            Route::prefix('age-groups')->name('age-groups.')->group(function () {
                Route::get('/', [SportsAgeGroupController::class, 'index'])->name('index');
                Route::post('/', [SportsAgeGroupController::class, 'store'])->name('store');
                Route::put('/global-cutoff', [SportsAgeGroupController::class, 'updateGlobalCutoff'])->name('global-cutoff.update');
                Route::post('/reset-defaults', [SportsAgeGroupController::class, 'resetDefaults'])->name('reset-defaults');
                Route::put('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'update'])->name('update');
                Route::delete('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'destroy'])->name('destroy');
            });
        }

        Route::prefix('catalog')->name('catalog.')->group(function () use ($slug, $eventType) {
            Route::get('/', [FestCatalogController::class, 'index'])->defaults('program', $slug)->name('index');
            Route::get('/master/{section?}', [FestCatalogController::class, 'master'])
                ->where('section', '[a-z0-9\-]+')
                ->name('master');
            Route::get('/list/{section?}', [FestCatalogController::class, 'list'])
                ->where('section', '[a-z0-9\-]+')
                ->name('list');
            Route::get('/assign', [FestCatalogController::class, 'assign'])->defaults('program', $slug)->name('assign');
            Route::get('/heads', [FestCatalogController::class, 'heads'])->defaults('program', $slug)->name('heads');
            Route::get('/heads/{head}/sample-id-card', [FestCatalogController::class, 'previewHeadIdCard'])->defaults('program', $slug)->name('heads.sample-id-card');
            Route::post('/heads', [FestCatalogController::class, 'storeHead'])->defaults('program', $slug)->name('heads.store');
            Route::post('/heads/sync', [FestCatalogController::class, 'syncHeads'])->defaults('program', $slug)->name('heads.sync');
            Route::get('/browse/{section}', [FestCatalogController::class, 'section'])
                ->where('section', '[a-z0-9\-]+')
                ->name('section');
            Route::post('/seed', [FestCatalogController::class, 'seed'])->name('seed');
            Route::post('/items', [FestCatalogController::class, 'store'])->defaults('program', $slug)->name('items.store');
            Route::put('/items/{item}', [FestCatalogController::class, 'update'])->name('items.update');
            Route::delete('/items/{item}', [FestCatalogController::class, 'destroy'])->name('items.destroy');
            Route::post('/bulk', [FestCatalogController::class, 'bulk'])->defaults('program', $slug)->name('bulk');
            Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent'])->name('import');

            if ($eventType) {
                $legacySections = array_column(FestCatalogSections::forEventType($eventType), 'slug');
                if ($legacySections !== []) {
                    Route::get('/{section}', [FestCatalogController::class, 'redirectLegacySection'])
                        ->whereIn('section', $legacySections)
                        ->name('legacy-section');
                }
            }
        });

        // Catch mistyped /{prefix}/{section} URLs (e.g. relative "relay" from catalog hub → /sports/relay).
        if ($eventType) {
            $catalogSections = array_column(FestCatalogSections::forEventType($eventType), 'slug');
            if ($catalogSections !== []) {
                Route::get('/{catalogSection}', function (string $tenantId, string $catalogSection) use ($prefix) {
                    return redirect("/sahodaya-admin/{$tenantId}/{$prefix}/catalog/master/{$catalogSection}", 301);
                })
                    ->whereIn('catalogSection', $catalogSections)
                    ->name('catalog-section-fallback');
            }
        }
    });

    Route::get("/programs/{$slug}", fn (string $tenantId) => redirect("/sahodaya-admin/{$tenantId}/{$prefix}", 301));
    Route::get("/programs/{$slug}/{path}", fn (string $tenantId, string $path) => redirect("/sahodaya-admin/{$tenantId}/{$prefix}/{$path}", 301))
        ->where('path', '.*');

    // Legacy `/programs/{slug}/catalog/*` mutation URLs (GET redirects above; POST cannot redirect).
    Route::prefix("programs/{$slug}/catalog")->group(function () use ($slug) {
        Route::post('/seed', [FestCatalogController::class, 'seed']);
        Route::post('/items', [FestCatalogController::class, 'store'])->defaults('program', $slug);
        Route::put('/items/{item}', [FestCatalogController::class, 'update']);
        Route::delete('/items/{item}', [FestCatalogController::class, 'destroy']);
        Route::post('/bulk', [FestCatalogController::class, 'bulk'])->defaults('program', $slug);
        Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent']);
    });
}

Route::prefix('mcq')->name('mcq-hub.')->group(function () {
    Route::get('/', [McqDashboardController::class, 'index'])->name('dashboard');
    Route::get('/question-banks', [McqDashboardController::class, 'questionBanks'])->name('question-banks');
    Route::get('/payments', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'index'])->name('payments');
    Route::post('/payments/{schoolFee}/approve', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{schoolFee}/reject', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{schoolFee}/proof', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'proof'])->name('payments.proof');
    Route::get('/grade-masters', [\App\Http\Controllers\SahodayaAdmin\McqGradeMasterController::class, 'index'])->name('grade-masters');
    Route::post('/grade-masters', [\App\Http\Controllers\SahodayaAdmin\McqGradeMasterController::class, 'store'])->name('grade-masters.store');
    Route::put('/grade-masters/{gradeMaster}', [\App\Http\Controllers\SahodayaAdmin\McqGradeMasterController::class, 'update'])->name('grade-masters.update');
    Route::get('/templates/hall-tickets', [\App\Http\Controllers\SahodayaAdmin\McqTemplateController::class, 'hallTickets'])->name('templates.hall-tickets');
    Route::post('/templates/hall-tickets', [\App\Http\Controllers\SahodayaAdmin\McqTemplateController::class, 'storeHallTicket'])->name('templates.hall-tickets.store');
    Route::get('/templates/certificates', [\App\Http\Controllers\SahodayaAdmin\McqTemplateController::class, 'certificates'])->name('templates.certificates');
    Route::post('/templates/certificates', [\App\Http\Controllers\SahodayaAdmin\McqTemplateController::class, 'storeCertificate'])->name('templates.certificates.store');
});

Route::prefix('fest')->name('fest.')->group(function () {
    Route::get('/appeals', [\App\Http\Controllers\SahodayaAdmin\FestAppealsHubController::class, 'index'])->name('appeals.index');
    Route::post('/appeals/{appeal}/resolve', [\App\Http\Controllers\SahodayaAdmin\FestAppealsHubController::class, 'resolve'])->name('appeals.resolve');
    Route::post('/appeals/{appeal}/mark-fee-paid', [\App\Http\Controllers\SahodayaAdmin\FestAppealsHubController::class, 'markFeePaid'])->name('appeals.mark-fee-paid');
    Route::get('/payments', [\App\Http\Controllers\SahodayaAdmin\FestPaymentsController::class, 'index'])->name('payments');
    Route::post('/payments/{schoolEventFee}/approve', [\App\Http\Controllers\SahodayaAdmin\FestPaymentsController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{schoolEventFee}/reject', [\App\Http\Controllers\SahodayaAdmin\FestPaymentsController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{schoolEventFee}/proof', [\App\Http\Controllers\SahodayaAdmin\FestPaymentsController::class, 'proof'])->name('payments.proof');
});

Route::get('/programs/custom', [FestEventController::class, 'programIndex'])
    ->defaults('program', 'custom')
    ->name('programs.custom');

Route::prefix('programs/custom/catalog')->name('programs.catalog.')->group(function () {
    Route::get('/', [FestCatalogController::class, 'index'])->defaults('program', 'custom')->name('index');
    Route::get('/master/{section?}', [FestCatalogController::class, 'master'])
        ->where('section', '[a-z0-9\-]+')
        ->name('master');
    Route::get('/list/{section?}', [FestCatalogController::class, 'list'])
        ->where('section', '[a-z0-9\-]+')
        ->name('list');
    Route::get('/assign', [FestCatalogController::class, 'assign'])->defaults('program', 'custom')->name('assign');
    Route::get('/browse/{section}', [FestCatalogController::class, 'section'])
        ->where('section', '[a-z0-9\-]+')
        ->name('section');
    Route::post('/seed', [FestCatalogController::class, 'seed'])->name('seed');
    Route::post('/items', [FestCatalogController::class, 'store'])->defaults('program', 'custom')->name('items.store');
    Route::put('/items/{item}', [FestCatalogController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [FestCatalogController::class, 'destroy'])->name('items.destroy');
    Route::post('/bulk', [FestCatalogController::class, 'bulk'])->defaults('program', 'custom')->name('bulk');
    Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent'])->name('import');
});

// Dynamic competition types (FRD-08 Phase 1) — any active FestCompetitionType nav_slug.
$reservedProgramSlugs = 'kalotsav|sports-meet|kids-fest|teacher-fest|english-fest|science-fest|custom|mcq|fest|events|catalog|taxonomy-masters|competition-types';
Route::get('/programs/{program}', [FestEventController::class, 'programIndex'])
    ->where('program', '^(?!'.$reservedProgramSlugs.')[a-z0-9\-]+$')
    ->name('programs.dynamic');

Route::prefix('programs/{program}/catalog')->where(['program' => '^(?!'.$reservedProgramSlugs.')[a-z0-9\-]+$'])->name('programs.dynamic.catalog.')->group(function () {
    Route::get('/', [FestCatalogController::class, 'index'])->name('index');
    Route::get('/master/{section?}', [FestCatalogController::class, 'master'])
        ->where('section', '[a-z0-9\-]+')
        ->name('master');
    Route::get('/list/{section?}', [FestCatalogController::class, 'list'])
        ->where('section', '[a-z0-9\-]+')
        ->name('list');
    Route::get('/assign', [FestCatalogController::class, 'assign'])->name('assign');
    Route::get('/browse/{section}', [FestCatalogController::class, 'section'])
        ->where('section', '[a-z0-9\-]+')
        ->name('section');
    Route::post('/seed', [FestCatalogController::class, 'seed'])->name('seed');
    Route::post('/items', [FestCatalogController::class, 'store'])->name('items.store');
    Route::put('/items/{item}', [FestCatalogController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [FestCatalogController::class, 'destroy'])->name('items.destroy');
    Route::post('/bulk', [FestCatalogController::class, 'bulk'])->name('bulk');
    Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent'])->name('import');
});

Route::get('/programs/{program}/{view}', function (string $tenantId, string $program, string $view) {
    abort_unless(in_array($view, ['registration', 'results'], true), 404);
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest', 'english-fest' => 'english-fest', 'science-fest' => 'science-fest'];
    if (isset($map[$program])) {
        return redirect("/sahodaya-admin/{$tenantId}/{$map[$program]}", 301);
    }

    return redirect("/sahodaya-admin/{$tenantId}/programs/{$program}");
})->whereIn('view', ['registration', 'results'])->where('program', '[a-z0-9\-]+');
