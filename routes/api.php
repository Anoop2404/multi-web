<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Public\SchoolApplicationApiController;
use App\Http\Controllers\Api\V1\School\DashboardApiController as SchoolDashboardApiController;
use App\Http\Controllers\Api\V1\School\ProfileApiController;
use App\Http\Controllers\Api\V1\School\RegistrationApiController;
use App\Http\Controllers\Api\V1\School\SetupApiController;
use App\Http\Controllers\Api\V1\School\StudentApiController;
use App\Http\Controllers\Api\V1\School\CircularApiController;
use App\Http\Controllers\Api\V1\School\FestApiController;
use App\Http\Controllers\Api\V1\School\McqApiController;
use App\Http\Controllers\Api\V1\School\TeacherApiController;
use App\Http\Controllers\Api\V1\School\TrainingApiController as SchoolTrainingApiController;
use App\Http\Controllers\Api\V1\Sahodaya\DashboardApiController as SahodayaDashboardApiController;
use App\Http\Controllers\Api\V1\Sahodaya\PaymentsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\ReportsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SchoolsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SettingsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\SubmissionsApiController;
use App\Http\Controllers\Api\V1\NotificationsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\EventsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\McqExamsApiController;
use App\Http\Controllers\Api\V1\Sahodaya\McqExamsWriteApiController;
use App\Http\Controllers\Api\V1\Sahodaya\TrainingApiController as SahodayaTrainingApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('docs', [\App\Http\Controllers\Api\V1\ApiDocsController::class, 'index']);

    Route::get('auth/login-branding', [AuthController::class, 'loginBranding']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('public/school-register', [SchoolApplicationApiController::class, 'form']);
    Route::post('public/school-register/validate', [SchoolApplicationApiController::class, 'validateField']);
    Route::post('public/school-register', [SchoolApplicationApiController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('notifications', [NotificationsApiController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationsApiController::class, 'markRead']);
        Route::post('notifications/fcm-token', [NotificationsApiController::class, 'storeFcmToken']);

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

                Route::get('teachers', [TeacherApiController::class, 'index']);
                Route::post('teachers', [TeacherApiController::class, 'store']);
                Route::delete('teachers/{teacher}', [TeacherApiController::class, 'destroy']);

                Route::get('mcq-exams', [McqApiController::class, 'index']);
                Route::post('mcq-exams', [McqApiController::class, 'store']);

                Route::get('training', [SchoolTrainingApiController::class, 'index']);
                Route::post('training', [SchoolTrainingApiController::class, 'store']);

                Route::get('circulars', [CircularApiController::class, 'index']);
                Route::post('circulars/{circular}/acknowledge', [CircularApiController::class, 'acknowledge']);

                Route::get('fest/events', [FestApiController::class, 'index']);
                Route::post('fest/registrations', [FestApiController::class, 'store']);
                Route::post('fest/registrations/import', [FestApiController::class, 'import']);
                Route::get('fest/registrations/import-template', [FestApiController::class, 'importTemplate']);
                Route::post('fest/registrations/{registration}/withdraw', [FestApiController::class, 'withdraw']);
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

                Route::get('events', [EventsApiController::class, 'index']);
                Route::get('events/{event}', [EventsApiController::class, 'show']);
                Route::post('events/{event}/registrations/{registration}/approve', [\App\Http\Controllers\Api\V1\Sahodaya\FestRegistrationsWriteApiController::class, 'approve']);
                Route::post('events/{event}/registrations/{registration}/reject', [\App\Http\Controllers\Api\V1\Sahodaya\FestRegistrationsWriteApiController::class, 'reject']);
                Route::post('events/{event}/registrations/bulk-approve', [\App\Http\Controllers\Api\V1\Sahodaya\FestRegistrationsWriteApiController::class, 'bulkApprove']);
                Route::get('mcq-exams', [McqExamsApiController::class, 'index']);
                Route::get('mcq-exams/{exam}', [McqExamsApiController::class, 'show']);
                Route::post('mcq-exams', [McqExamsWriteApiController::class, 'store']);
                Route::post('mcq-exams/{exam}/registrations/{registration}/marks', [McqExamsWriteApiController::class, 'storeMark']);
                Route::get('training', [SahodayaTrainingApiController::class, 'index']);
                Route::get('training/{program}', [SahodayaTrainingApiController::class, 'show']);
            });
    });
});
