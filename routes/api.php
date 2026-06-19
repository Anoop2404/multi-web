<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Public\SchoolApplicationApiController;
use App\Http\Controllers\Api\V1\School\DashboardApiController as SchoolDashboardApiController;
use App\Http\Controllers\Api\V1\School\ProfileApiController;
use App\Http\Controllers\Api\V1\School\RegistrationApiController;
use App\Http\Controllers\Api\V1\School\SetupApiController;
use App\Http\Controllers\Api\V1\School\StudentApiController;
use App\Http\Controllers\Api\V1\Sahodaya\DashboardApiController as SahodayaDashboardApiController;
use App\Http\Controllers\Api\V1\Sahodaya\PaymentsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\ReportsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SchoolsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SettingsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SubmissionsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('auth/login-branding', [AuthController::class, 'loginBranding']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('public/school-register', [SchoolApplicationApiController::class, 'form']);
    Route::post('public/school-register', [SchoolApplicationApiController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::prefix('school/{tenantId}')
            ->middleware('school.admin.api')
            ->group(function () {
                Route::get('dashboard', [SchoolDashboardApiController::class, 'index']);

                Route::get('setup/code', [SetupApiController::class, 'show']);
                Route::post('setup/code', [SetupApiController::class, 'saveCode']);

                Route::get('students/import/template', [StudentApiController::class, 'importTemplate']);
                Route::post('students/import', [StudentApiController::class, 'import']);
                Route::get('students', [StudentApiController::class, 'index']);
                Route::post('students', [StudentApiController::class, 'store']);
                Route::put('students/{student}', [StudentApiController::class, 'update']);
                Route::delete('students/{student}', [StudentApiController::class, 'destroy']);
                Route::post('students/{student}/photo', [StudentApiController::class, 'uploadPhoto']);
                Route::get('students/{student}/photo', [StudentApiController::class, 'showPhoto']);

                Route::get('registration', [RegistrationApiController::class, 'index']);
                Route::post('registration/begin', [RegistrationApiController::class, 'begin']);
                Route::get('registration/submission-students', [RegistrationApiController::class, 'submissionStudents']);
                Route::post('registration/submission-students', [RegistrationApiController::class, 'storeSubmissionStudent']);
                Route::delete('registration/submission-students/{student}', [RegistrationApiController::class, 'destroySubmissionStudent']);
                Route::get('registration/counts', [RegistrationApiController::class, 'counts']);
                Route::post('registration/counts', [RegistrationApiController::class, 'saveCounts']);
                Route::get('registration/teachers', [RegistrationApiController::class, 'teachers']);
                Route::post('registration/teachers', [RegistrationApiController::class, 'storeTeacher']);
                Route::delete('registration/teachers/{teacher}', [RegistrationApiController::class, 'destroyTeacher']);
                Route::post('registration/submit-track', [RegistrationApiController::class, 'submitTrack']);
                Route::post('registration/payment', [RegistrationApiController::class, 'uploadPayment']);
                Route::get('registration/payments/{payment}/proof', [RegistrationApiController::class, 'paymentProof']);

                Route::get('registration/profile', [ProfileApiController::class, 'show']);
                Route::put('registration/profile', [ProfileApiController::class, 'updateProfile']);
                Route::put('registration/account', [ProfileApiController::class, 'updateAccount']);
            });

        Route::prefix('sahodaya/{tenantId}')
            ->middleware('sahodaya.admin.api')
            ->group(function () {
                Route::get('dashboard', [SahodayaDashboardApiController::class, 'index']);

                Route::get('schools', [SchoolsApiController::class, 'index']);
                Route::get('schools/{school}', [SchoolsApiController::class, 'show']);
                Route::post('schools/{school}/reject', [SchoolsApiController::class, 'reject']);

                Route::get('payments', [PaymentsApiController::class, 'index']);
                Route::post('payments/{payment}/verify', [PaymentsApiController::class, 'verify']);
                Route::get('payments/{payment}/proof', [PaymentsApiController::class, 'proof']);

                Route::get('submissions', [SubmissionsApiController::class, 'index']);
                Route::get('submissions/submission-students/{student}/image', [SubmissionsApiController::class, 'studentImage']);
                Route::get('submissions/{submission}', [SubmissionsApiController::class, 'show']);

                Route::get('reports/summary', [ReportsApiController::class, 'summary']);
                Route::get('settings', [SettingsApiController::class, 'show']);
            });
    });
});
