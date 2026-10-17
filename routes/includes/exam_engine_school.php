<?php

// Online exam engine routes (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md). Included from routes/web.php.

use App\Http\Controllers\ExamEngine\SchoolLiveMonitorController;
use Illuminate\Support\Facades\Route;

// School panel → Talent Search exam → Live monitor (own school only). Inside the
// school-admin/{tenantId} group, so names are school.mcq.live.*. No broadcast/pace/close here.
Route::prefix('mcq/{exam}/live')
    ->name('mcq.live.')
    ->where(['exam' => '[0-9]+', 'regId' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [SchoolLiveMonitorController::class, 'show'])->name('show');
        Route::get('/data', [SchoolLiveMonitorController::class, 'data'])->name('data');
        Route::post('/attempts/{regId}/action', [SchoolLiveMonitorController::class, 'action'])->name('action');
        Route::post('/attempts/{regId}/passcode', [SchoolLiveMonitorController::class, 'passcode'])->name('passcode');
    });
