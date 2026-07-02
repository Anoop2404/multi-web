<?php

use App\Http\Controllers\SchoolAdmin\FestRegistrationController;
use App\Http\Controllers\SchoolAdmin\FestSchoolReportController;
use App\Http\Controllers\SchoolAdmin\KidsFestController;
use App\Http\Controllers\SchoolAdmin\KalotsavController;
use App\Http\Controllers\SchoolAdmin\McqController;
use App\Http\Controllers\SchoolAdmin\McqRegistrationController;
use App\Http\Controllers\SchoolAdmin\SportsMeetController;
use App\Http\Controllers\SchoolAdmin\TeacherFestController;
use App\Http\Controllers\SchoolAdmin\TrainingController;
use App\Http\Controllers\SchoolAdmin\TrainingRegistrationController;
use Illuminate\Support\Facades\Route;

$festProgramSlugs = 'kalotsav|sports-meet|kids-fest|teacher-fest|custom';

$festPrograms = [
    ['prefix' => 'kalotsav', 'slug' => 'kalotsav', 'controller' => KalotsavController::class],
    ['prefix' => 'sports', 'slug' => 'sports-meet', 'controller' => SportsMeetController::class],
    ['prefix' => 'kids-fest', 'slug' => 'kids-fest', 'controller' => KidsFestController::class],
    ['prefix' => 'teacher-fest', 'slug' => 'teacher-fest', 'controller' => TeacherFestController::class],
];

foreach ($festPrograms as $cfg) {
    $prefix = $cfg['prefix'];
    $slug = $cfg['slug'];
    $controller = $cfg['controller'];

    Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller, $slug) {
        Route::get('/', [$controller, 'hub'])->name('hub');
        Route::get('/my-events', [$controller, 'myEvents'])->name('my-events');
        Route::get('/registration', [$controller, 'registration'])->name('registration');
        Route::get('/results', [$controller, 'results'])->name('results');
        Route::get('/reports', [$controller, 'reports'])->name('reports.index');
        Route::get('/qualifiers', [$controller, 'qualifiers'])->name('qualifiers');
        Route::get('/qualifiers/export', [FestSchoolReportController::class, 'exportQualifiers'])
            ->defaults('program', $slug)
            ->name('qualifiers.export');
        Route::get('/import-template', [FestRegistrationController::class, 'importTemplate'])
            ->defaults('program', $slug)
            ->name('import-template');
        Route::post('/import', [FestRegistrationController::class, 'importStore'])
            ->defaults('program', $slug)
            ->name('import');
        Route::post('/register', [FestRegistrationController::class, 'store'])
            ->defaults('program', $slug)
            ->name('register');
        Route::post('/registrations/{registration}/withdraw', [FestRegistrationController::class, 'withdraw'])
            ->defaults('program', $slug)
            ->name('registrations.withdraw');
        Route::post('/events/{event}/payment', [FestRegistrationController::class, 'uploadEventPayment'])
            ->defaults('program', $slug)
            ->name('event.payment');
        Route::get('/events/{event}/receipt', [FestRegistrationController::class, 'feeReceipt'])
            ->defaults('program', $slug)
            ->name('event.receipt');
        Route::get('/events/{event}/invoice', [FestRegistrationController::class, 'eventInvoice'])
            ->defaults('program', $slug)
            ->name('event.invoice');
        Route::get('/fest-day/{event}', [FestRegistrationController::class, 'festDay'])
            ->defaults('program', $slug)
            ->name('fest-day');
        Route::get('/reports/{event}/participation', [FestSchoolReportController::class, 'participation'])
            ->defaults('program', $slug)
            ->name('reports.participation');
        Route::get('/reports/{event}/participation/export', [FestSchoolReportController::class, 'exportParticipation'])
            ->defaults('program', $slug)
            ->name('reports.participation.export');
        Route::get('/reports/{event}/student-wise', [FestSchoolReportController::class, 'studentWise'])
            ->defaults('program', $slug)
            ->name('reports.student-wise');
        Route::get('/reports/{event}/student-wise/export', [FestSchoolReportController::class, 'exportStudentWise'])
            ->defaults('program', $slug)
            ->name('reports.student-wise.export');
        Route::get('/reports/{event}/teacher-wise', [FestSchoolReportController::class, 'teacherWise'])
            ->defaults('program', $slug)
            ->name('reports.teacher-wise');
        Route::get('/reports/{event}/teacher-wise/export', [FestSchoolReportController::class, 'exportTeacherWise'])
            ->defaults('program', $slug)
            ->name('reports.teacher-wise.export');
        Route::get('/reports/{event}/item-wise', [FestSchoolReportController::class, 'itemWise'])
            ->defaults('program', $slug)
            ->name('reports.item-wise');
        Route::get('/reports/{event}/item-wise/export', [FestSchoolReportController::class, 'exportItemWise'])
            ->defaults('program', $slug)
            ->name('reports.item-wise.export');
        Route::get('/reports/{event}/admit-cards', [FestSchoolReportController::class, 'admitCards'])
            ->defaults('program', $slug)
            ->name('reports.admit-cards');
        Route::get('/reports/{event}/registration-register', [FestSchoolReportController::class, 'registrationRegister'])
            ->defaults('program', $slug)
            ->name('reports.registration-register');
        Route::get('/reports/{event}/registration-register/export', [FestSchoolReportController::class, 'exportRegistrationRegister'])
            ->defaults('program', $slug)
            ->name('reports.registration-register.export');
        Route::get('/reports/{event}/id-cards', [FestSchoolReportController::class, 'idCards'])
            ->defaults('program', $slug)
            ->name('reports.id-cards');
        Route::get('/reports/{event}/id-cards/cards', [FestSchoolReportController::class, 'idCardsJson'])
            ->defaults('program', $slug)
            ->name('reports.id-cards.cards');
        Route::get('/reports/{event}/id-cards/pdf', [FestSchoolReportController::class, 'idCardsPdf'])
            ->defaults('program', $slug)
            ->name('reports.id-cards.pdf');
    });
}

Route::prefix('sports')->name('sports.')->group(function () {
    Route::get('/my-event/{event}/{tab?}', [SportsMeetController::class, 'myEvent'])
        ->where('tab', 'overview|marks|results|link|winners')
        ->name('my-event');
    Route::get('/sahodaya-event/{event}', [SportsMeetController::class, 'sahodayaEvent'])->name('sahodaya-event');
    Route::post('/my-event/{event}/link-parent', [SportsMeetController::class, 'linkParent'])->name('my-event.link-parent');
    Route::post('/my-event/{event}/marks', [SportsMeetController::class, 'storeMark'])->name('my-event.marks.store');
    Route::post('/my-event/{event}/items/{item}/auto-rank', [SportsMeetController::class, 'autoRankItem'])->name('my-event.auto-rank');
    Route::get('/submit-winners', [SportsMeetController::class, 'submitWinners'])->name('submit-winners');
    Route::post('/submit-winners', [SportsMeetController::class, 'storeWinners'])->name('submit-winners.store');
});

Route::prefix('mcq')->name('mcq.')->group(function () {
    Route::get('/', [McqController::class, 'hub'])->name('index');
    Route::get('/{exam}/reports/registration/export', [\App\Http\Controllers\SchoolAdmin\McqReportController::class, 'exportRegistration'])->name('reports.registration.export');
    Route::get('/{exam}/reports/attendance/export', [\App\Http\Controllers\SchoolAdmin\McqReportController::class, 'exportAttendance'])->name('reports.attendance.export');
    Route::get('/{exam}/{tab?}', [McqController::class, 'exam'])->name('exam')->where('tab', 'register|students|hall-tickets|fee|results|toppers|reports');
    Route::post('/{exam}/register', [McqController::class, 'register']);
    Route::post('/{exam}/register-by-class', [McqController::class, 'bulkRegister'])->name('register-by-class');
    Route::post('/{exam}/register-bulk', [McqController::class, 'bulkRegister']);
    Route::post('/{exam}/fee', [McqController::class, 'uploadFee']);
    Route::get('/{exam}/hall-tickets/pdf', [McqController::class, 'hallTicketsPdf']);
    Route::post('/', [McqRegistrationController::class, 'store']);
    Route::post('/bulk', [McqRegistrationController::class, 'bulkStore']);
    Route::post('/{exam}/school-payment', [McqRegistrationController::class, 'uploadSchoolPayment']);
    Route::post('/{registration}/payment', [McqRegistrationController::class, 'uploadPayment']);
});
Route::get('/mcq-exams', fn (string $tenantId) => redirect("/school-admin/{$tenantId}/mcq", 301));

Route::prefix('training')->name('training.')->group(function () {
    Route::get('/', [TrainingController::class, 'hub'])->name('index');
    Route::post('/', [TrainingRegistrationController::class, 'store']);
    Route::post('/{registration}/payment', [TrainingRegistrationController::class, 'uploadPayment']);
});

Route::get("/programs/{program}", function (string $tenantId, string $program) use ($festProgramSlugs) {
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest'];
    if (isset($map[$program])) {
        return redirect("/school-admin/{$tenantId}/{$map[$program]}", 301);
    }

    return redirect("/school-admin/{$tenantId}/programs/{$program}/hub");
})->where('program', $festProgramSlugs);

Route::get("/programs/{program}/{path}", function (string $tenantId, string $program, string $path) {
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest'];
    if (isset($map[$program])) {
        return redirect("/school-admin/{$tenantId}/{$map[$program]}/{$path}", 301);
    }

    return redirect("/school-admin/{$tenantId}/programs/{$program}/{$path}", 301);
})->where('program', $festProgramSlugs)->where('path', '.*');
