<?php

use App\Http\Controllers\SahodayaAdmin\AthleticRecordsDashboardController;
use App\Http\Controllers\SahodayaAdmin\FestCatalogController;
use App\Http\Controllers\SahodayaAdmin\FestEventController;
use App\Http\Controllers\SahodayaAdmin\KidsFestProgramController;
use App\Http\Controllers\SahodayaAdmin\KalotsavProgramController;
use App\Http\Controllers\SahodayaAdmin\McqDashboardController;
use App\Http\Controllers\SahodayaAdmin\SportsAgeGroupController;
use App\Http\Controllers\SahodayaAdmin\SportsProgramController;
use App\Http\Controllers\SahodayaAdmin\TeacherFestProgramController;
use Illuminate\Support\Facades\Route;

$sahodayaFestPrograms = [
    ['prefix' => 'kalotsav', 'slug' => 'kalotsav', 'controller' => KalotsavProgramController::class],
    ['prefix' => 'sports', 'slug' => 'sports-meet', 'controller' => SportsProgramController::class],
    ['prefix' => 'kids-fest', 'slug' => 'kids-fest', 'controller' => KidsFestProgramController::class],
    ['prefix' => 'teacher-fest', 'slug' => 'teacher-fest', 'controller' => TeacherFestProgramController::class],
];

foreach ($sahodayaFestPrograms as $cfg) {
    $prefix = $cfg['prefix'];
    $slug = $cfg['slug'];
    $controller = $cfg['controller'];

    Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller, $slug, $prefix) {
        Route::get('/', [$controller, 'dashboard'])->name('dashboard');

        if ($prefix === 'kalotsav') {
            Route::get('/school-rounds', [KalotsavProgramController::class, 'schoolRounds'])->name('school-rounds');
        }

        if ($prefix === 'sports') {
            Route::get('/records', [AthleticRecordsDashboardController::class, 'index'])->name('records');
            Route::get('/championship', [SportsProgramController::class, 'championship'])->name('championship');

            Route::prefix('age-groups')->name('age-groups.')->group(function () {
                Route::get('/', [SportsAgeGroupController::class, 'index'])->name('index');
                Route::post('/', [SportsAgeGroupController::class, 'store'])->name('store');
                Route::post('/reset-defaults', [SportsAgeGroupController::class, 'resetDefaults'])->name('reset-defaults');
                Route::put('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'update'])->name('update');
                Route::delete('/{sportsAgeGroup}', [SportsAgeGroupController::class, 'destroy'])->name('destroy');
            });
        }

        Route::prefix('catalog')->name('catalog.')->group(function () use ($slug) {
            Route::get('/', [FestCatalogController::class, 'index'])->defaults('program', $slug)->name('index');
            Route::get('/master/{section?}', [FestCatalogController::class, 'master'])
                ->where('section', '[a-z0-9\-]+')
                ->defaults('program', $slug)
                ->name('master');
            Route::get('/list/{section?}', [FestCatalogController::class, 'list'])
                ->where('section', '[a-z0-9\-]+')
                ->defaults('program', $slug)
                ->name('list');
            Route::get('/assign', [FestCatalogController::class, 'assign'])->defaults('program', $slug)->name('assign');
            Route::get('/browse/{section}', [FestCatalogController::class, 'section'])
                ->where('section', '[a-z0-9\-]+')
                ->defaults('program', $slug)
                ->name('section');
            Route::post('/seed', [FestCatalogController::class, 'seed'])->defaults('program', $slug)->name('seed');
            Route::post('/items', [FestCatalogController::class, 'store'])->defaults('program', $slug)->name('items.store');
            Route::put('/items/{item}', [FestCatalogController::class, 'update'])->defaults('program', $slug)->name('items.update');
            Route::delete('/items/{item}', [FestCatalogController::class, 'destroy'])->defaults('program', $slug)->name('items.destroy');
            Route::post('/bulk', [FestCatalogController::class, 'bulk'])->defaults('program', $slug)->name('bulk');
            Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent'])->defaults('program', $slug)->name('import');
        });
    });

    Route::get("/programs/{$slug}", fn (string $tenantId) => redirect("/sahodaya-admin/{$tenantId}/{$prefix}", 301));
    Route::get("/programs/{$slug}/{path}", fn (string $tenantId, string $path) => redirect("/sahodaya-admin/{$tenantId}/{$prefix}/{$path}", 301))
        ->where('path', '.*');
}

Route::prefix('mcq')->name('mcq-hub.')->group(function () {
    Route::get('/', [McqDashboardController::class, 'index'])->name('dashboard');
    Route::get('/question-banks', [McqDashboardController::class, 'questionBanks'])->name('question-banks');
    Route::get('/payments', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'index'])->name('payments');
    Route::post('/payments/{schoolFee}/approve', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'approve'])->name('payments.approve');
    Route::get('/payments/{schoolFee}/proof', [\App\Http\Controllers\SahodayaAdmin\McqPaymentsController::class, 'proof'])->name('payments.proof');
});

Route::prefix('fest')->name('fest.')->group(function () {
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
        ->defaults('program', 'custom')
        ->name('master');
    Route::get('/list/{section?}', [FestCatalogController::class, 'list'])
        ->where('section', '[a-z0-9\-]+')
        ->defaults('program', 'custom')
        ->name('list');
    Route::get('/assign', [FestCatalogController::class, 'assign'])->defaults('program', 'custom')->name('assign');
    Route::get('/browse/{section}', [FestCatalogController::class, 'section'])
        ->where('section', '[a-z0-9\-]+')
        ->defaults('program', 'custom')
        ->name('section');
    Route::post('/seed', [FestCatalogController::class, 'seed'])->defaults('program', 'custom')->name('seed');
    Route::post('/items', [FestCatalogController::class, 'store'])->defaults('program', 'custom')->name('items.store');
    Route::put('/items/{item}', [FestCatalogController::class, 'update'])->defaults('program', 'custom')->name('items.update');
    Route::delete('/items/{item}', [FestCatalogController::class, 'destroy'])->defaults('program', 'custom')->name('items.destroy');
    Route::post('/bulk', [FestCatalogController::class, 'bulk'])->defaults('program', 'custom')->name('bulk');
    Route::post('/import/{event}', [FestCatalogController::class, 'importToEvent'])->defaults('program', 'custom')->name('import');
});

Route::get('/programs/{program}/{view}', function (string $tenantId, string $program, string $view) {
    abort_unless(in_array($view, ['registration', 'results'], true), 404);
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest'];
    if (isset($map[$program])) {
        return redirect("/sahodaya-admin/{$tenantId}/{$map[$program]}", 301);
    }

    return redirect("/sahodaya-admin/{$tenantId}/programs/{$program}");
})->whereIn('view', ['registration', 'results'])->whereIn('program', ['kalotsav', 'sports-meet', 'kids-fest', 'teacher-fest', 'custom']);
