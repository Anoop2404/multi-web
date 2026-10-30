<?php

// Online exam engine routes (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md). Included from routes/web.php.

use App\Http\Controllers\ExamEngine\PlatformBookingsController;
use Illuminate\Support\Facades\Route;

// Super admin → Online exam bookings calendar. Inside the admin super.admin group: admin.exam-engine.*.
Route::get('/exam-engine/bookings', [PlatformBookingsController::class, 'index'])->name('exam-engine.bookings');

// Super admin → Online MCQ exam concurrency rate limiting & capacity settings
Route::prefix('mcq')->name('mcq.')->group(function () {
    Route::get('/concurrency-settings', [\App\Http\Controllers\Admin\McqConcurrencySettingsController::class, 'index'])->name('concurrency-settings');
    Route::put('/concurrency-settings', [\App\Http\Controllers\Admin\McqConcurrencySettingsController::class, 'updateGlobal'])->name('concurrency-settings.update');
    Route::put('/concurrency-settings/override/{tenant}', [\App\Http\Controllers\Admin\McqConcurrencySettingsController::class, 'updateOverride'])->name('concurrency-settings.override');
    Route::delete('/concurrency-settings/override/{tenant}', [\App\Http\Controllers\Admin\McqConcurrencySettingsController::class, 'deleteOverride'])->name('concurrency-settings.override.delete');
});
