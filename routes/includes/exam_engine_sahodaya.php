<?php

// Online exam engine routes (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md). Included from routes/web.php.

use App\Http\Controllers\ExamEngine\SahodayaLiveMonitorController;
use Illuminate\Support\Facades\Route;

// Sahodaya panel → Talent Search exam → Live monitor (exam-engine/CONTRACT.md §3).
// Inside the sahodaya-admin/{tenantId} group, so names are sahodaya.mcq.live.*.
Route::prefix('mcq-exams/{exam}/live')
    ->name('mcq.live.')
    ->where(['exam' => '[0-9]+', 'regId' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [SahodayaLiveMonitorController::class, 'show'])->name('show');
        Route::get('/data', [SahodayaLiveMonitorController::class, 'data'])->name('data');
        Route::post('/attempts/{regId}/action', [SahodayaLiveMonitorController::class, 'action'])->name('action');
        Route::post('/attempts/{regId}/passcode', [SahodayaLiveMonitorController::class, 'passcode'])->name('passcode');
        Route::post('/broadcast', [SahodayaLiveMonitorController::class, 'broadcast'])->name('broadcast');
        Route::post('/pace', [SahodayaLiveMonitorController::class, 'pace'])->name('pace');
        Route::post('/extend-all', [SahodayaLiveMonitorController::class, 'extendAll'])->name('extend-all');
        Route::post('/hold', [SahodayaLiveMonitorController::class, 'hold'])->name('hold');
        Route::post('/close', [SahodayaLiveMonitorController::class, 'close'])->name('close');
    });
