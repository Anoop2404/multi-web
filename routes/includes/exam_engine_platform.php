<?php

// Online exam engine routes (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md). Included from routes/web.php.

use App\Http\Controllers\ExamEngine\PlatformBookingsController;
use Illuminate\Support\Facades\Route;

// Super admin → Online exam bookings calendar. Inside the admin super.admin group: admin.exam-engine.*.
Route::get('/exam-engine/bookings', [PlatformBookingsController::class, 'index'])->name('exam-engine.bookings');
