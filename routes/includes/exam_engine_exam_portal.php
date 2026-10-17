<?php

// Online exam engine routes (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md). Included from routes/web.php.

use App\Http\Controllers\ExamEngine\ExamPortalLiveMonitorController;
use Illuminate\Support\Facades\Route;

// Exam portal → exam → Live monitor. Inside the portal/exam/{tenantId} group, so names are
// portal.exam.live.*. Exam controllers act; assigned exam staff only watch.
Route::prefix('exams/{exam}/live')
    ->name('live.')
    ->where(['exam' => '[0-9]+', 'regId' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [ExamPortalLiveMonitorController::class, 'show'])->name('show');
        Route::get('/data', [ExamPortalLiveMonitorController::class, 'data'])->name('data');
        Route::post('/attempts/{regId}/action', [ExamPortalLiveMonitorController::class, 'action'])->name('action');
        Route::post('/attempts/{regId}/passcode', [ExamPortalLiveMonitorController::class, 'passcode'])->name('passcode');
        Route::post('/broadcast', [ExamPortalLiveMonitorController::class, 'broadcast'])->name('broadcast');
        Route::post('/pace', [ExamPortalLiveMonitorController::class, 'pace'])->name('pace');
        Route::post('/extend-all', [ExamPortalLiveMonitorController::class, 'extendAll'])->name('extend-all');
        Route::post('/hold', [ExamPortalLiveMonitorController::class, 'hold'])->name('hold');
        Route::post('/close', [ExamPortalLiveMonitorController::class, 'close'])->name('close');
    });
