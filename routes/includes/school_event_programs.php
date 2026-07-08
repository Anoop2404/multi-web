<?php

use App\Http\Controllers\SchoolAdmin\FestClashRequestController;
use App\Http\Controllers\SchoolAdmin\FestSubstitutionRequestController;
use App\Http\Controllers\SchoolAdmin\FestRegistrationController;
use App\Http\Controllers\SchoolAdmin\FestSchoolReportController;
use App\Http\Controllers\SchoolAdmin\KidsFestController;
use App\Http\Controllers\SchoolAdmin\KalotsavController;
use App\Http\Controllers\SchoolAdmin\McqController;
use App\Http\Controllers\SchoolAdmin\McqRegistrationController;
use App\Http\Controllers\SchoolAdmin\EnglishFestController;
use App\Http\Controllers\SchoolAdmin\ScienceFestController;
use App\Http\Controllers\SchoolAdmin\SportsMeetController;
use App\Http\Controllers\SchoolAdmin\TeacherFestController;
use App\Http\Controllers\SchoolAdmin\CustomFestController;
use App\Http\Controllers\SchoolAdmin\TrainingController;
use App\Http\Controllers\SchoolAdmin\TrainingRegistrationController;
use Illuminate\Support\Facades\Route;

$festProgramSlugs = 'kalotsav|sports-meet|kids-fest|teacher-fest|english-fest|science-fest|custom';

$festPrograms = [
    ['prefix' => 'kalotsav', 'slug' => 'kalotsav', 'controller' => KalotsavController::class],
    ['prefix' => 'sports', 'slug' => 'sports-meet', 'controller' => SportsMeetController::class],
    ['prefix' => 'kids-fest', 'slug' => 'kids-fest', 'controller' => KidsFestController::class],
    ['prefix' => 'teacher-fest', 'slug' => 'teacher-fest', 'controller' => TeacherFestController::class],
    ['prefix' => 'english-fest', 'slug' => 'english-fest', 'controller' => EnglishFestController::class],
    ['prefix' => 'science-fest', 'slug' => 'science-fest', 'controller' => ScienceFestController::class],
    ['prefix' => 'custom', 'slug' => 'custom', 'controller' => CustomFestController::class],
];

foreach ($festPrograms as $cfg) {
    $prefix = $cfg['prefix'];
    $slug = $cfg['slug'];
    $controller = $cfg['controller'];

    Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller, $slug, $prefix) {
        Route::get('/', [$controller, 'hub'])->name('hub');
        Route::get('/my-events', [$controller, 'myEvents'])->name('my-events');
        Route::get('/registration', [$controller, 'registration'])->name('registration');
        Route::get('/events/{event}/overview', [FestRegistrationController::class, 'eventOverview'])
            ->defaults('program', $slug)
            ->name('event.overview');
        Route::get('/events/{event}/registration', [FestRegistrationController::class, 'eventRegistration'])
            ->defaults('program', $slug)
            ->name('event.registration');
        Route::get('/events/{event}/eligible-students', [FestRegistrationController::class, 'eligibleStudents'])
            ->defaults('program', $slug)
            ->name('event.eligible-students');
        if ($prefix === 'sports') {
            Route::get('/item-registration', [FestRegistrationController::class, 'itemRegistrationEntry'])
                ->defaults('program', $slug)
                ->name('item-registration');
            Route::get('/events/{event}/items', [FestRegistrationController::class, 'eventItemRegistration'])
                ->defaults('program', $slug)
                ->name('event.items');
        }
        Route::get('/results', [$controller, 'results'])->name('results');
        Route::get('/reports', [$controller, 'reports'])->name('reports.index');
        Route::get('/reports/{event}', [FestSchoolReportController::class, 'eventHub'])
            ->defaults('program', $slug)
            ->name('reports.event');
        Route::get('/reports/{event}/export/{exportType}', [FestSchoolReportController::class, 'export'])
            ->defaults('program', $slug)
            ->name('reports.export');
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
        Route::post('/events/{event}/register-students', [\App\Http\Controllers\SchoolAdmin\FestEventStudentRegistrationController::class, 'store'])
            ->defaults('program', $slug)
            ->name('event.register-students');
        Route::post('/events/{event}/bulk-assign', [\App\Http\Controllers\SchoolAdmin\FestEventStudentRegistrationController::class, 'bulkAssign'])
            ->defaults('program', $slug)
            ->name('event.bulk-assign');
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
        Route::get('/reports/{event}/participation/pdf', [FestSchoolReportController::class, 'exportParticipationPdf'])
            ->defaults('program', $slug)
            ->name('reports.participation.pdf');
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
        Route::get('/reports/{event}/item-wise/pdf', [FestSchoolReportController::class, 'exportItemWisePdf'])
            ->defaults('program', $slug)
            ->name('reports.item-wise.pdf');
        Route::get('/reports/{event}/admit-cards', [FestSchoolReportController::class, 'admitCards'])
            ->defaults('program', $slug)
            ->name('reports.admit-cards');
        Route::get('/reports/{event}/registration-register', [FestSchoolReportController::class, 'registrationRegister'])
            ->defaults('program', $slug)
            ->name('reports.registration-register');
        Route::get('/reports/{event}/registration-register/export', [FestSchoolReportController::class, 'exportRegistrationRegister'])
            ->defaults('program', $slug)
            ->name('reports.registration-register.export');
        Route::get('/reports/{event}/registration-register/pdf', [FestSchoolReportController::class, 'exportRegistrationRegisterPdf'])
            ->defaults('program', $slug)
            ->name('reports.registration-register.pdf');
        Route::get('/reports/{event}/id-cards', [FestSchoolReportController::class, 'idCards'])
            ->defaults('program', $slug)
            ->name('reports.id-cards');
        Route::get('/reports/{event}/id-cards/cards', [FestSchoolReportController::class, 'idCardsJson'])
            ->defaults('program', $slug)
            ->name('reports.id-cards.cards');
        Route::get('/reports/{event}/id-cards/pdf', [FestSchoolReportController::class, 'idCardsPdf'])
            ->defaults('program', $slug)
            ->name('reports.id-cards.pdf');
        Route::get('/reports/{event}/id-cards/pdf-all-heads', [FestSchoolReportController::class, 'idCardsPdfAllHeads'])
            ->defaults('program', $slug)
            ->name('reports.id-cards.pdf-all-heads');
        Route::get('/reports/{event}/fee-summary', [FestSchoolReportController::class, 'feeSummary'])
            ->defaults('program', $slug)
            ->name('reports.fee-summary');
        Route::get('/reports/{event}/discipline-participation', [FestSchoolReportController::class, 'disciplineParticipation'])
            ->defaults('program', $slug)
            ->name('reports.discipline-participation');
        Route::get('/reports/{event}/discipline-participation/pdf', [FestSchoolReportController::class, 'exportDisciplinePdf'])
            ->defaults('program', $slug)
            ->name('reports.discipline-participation.pdf');
        Route::get('/reports/{event}/head-wise', [FestSchoolReportController::class, 'headWise'])
            ->defaults('program', $slug)
            ->name('reports.head-wise');
        Route::get('/reports/{event}/head-wise/pdf', [FestSchoolReportController::class, 'exportHeadWisePdf'])
            ->defaults('program', $slug)
            ->name('reports.head-wise.pdf');
        Route::get('/reports/{event}/item-counts', [FestSchoolReportController::class, 'itemCounts'])
            ->defaults('program', $slug)
            ->name('reports.item-counts');
        Route::get('/reports/{event}/item-counts/pdf', [FestSchoolReportController::class, 'exportItemCountsPdf'])
            ->defaults('program', $slug)
            ->name('reports.item-counts.pdf');
        Route::get('/reports/{event}/item-counts/export', [FestSchoolReportController::class, 'exportItemCountsExcel'])
            ->defaults('program', $slug)
            ->name('reports.item-counts.export');
        Route::get('/reports/{event}/items/{item}/participants', [FestSchoolReportController::class, 'itemParticipantsJson'])
            ->defaults('program', $slug)
            ->whereNumber('item')
            ->name('reports.item-participants');
        Route::get('/reports/{event}/items/{item}/participants/pdf', [FestSchoolReportController::class, 'exportItemParticipantsPdf'])
            ->defaults('program', $slug)
            ->whereNumber('item')
            ->name('reports.item-participants.pdf');
        Route::get('/reports/{event}/items/{item}/participants/export', [FestSchoolReportController::class, 'exportItemParticipantsExcel'])
            ->defaults('program', $slug)
            ->whereNumber('item')
            ->name('reports.item-participants.export');
        Route::get('/reports/{event}/assignment-completeness', [FestSchoolReportController::class, 'assignmentCompleteness'])
            ->defaults('program', $slug)
            ->name('reports.assignment-completeness');
        Route::get('/reports/{event}/assignment-completeness/export', [FestSchoolReportController::class, 'exportAssignmentCompleteness'])
            ->defaults('program', $slug)
            ->name('reports.assignment-completeness.export');
        Route::get('/reports/{event}/numbering-register', [FestSchoolReportController::class, 'numberingRegister'])
            ->defaults('program', $slug)
            ->name('reports.numbering-register');
        Route::get('/reports/{event}/numbering-register/export', [FestSchoolReportController::class, 'exportNumberingRegister'])
            ->defaults('program', $slug)
            ->name('reports.numbering-register.export');
        Route::get('/reports/{event}/pending-approvals', [FestSchoolReportController::class, 'pendingApprovals'])
            ->defaults('program', $slug)
            ->name('reports.pending-approvals');
        Route::get('/reports/{event}/pending-approvals/export', [FestSchoolReportController::class, 'exportPendingApprovals'])
            ->defaults('program', $slug)
            ->name('reports.pending-approvals.export');
        Route::get('/reports/{event}/schedule-clashes', [FestSchoolReportController::class, 'scheduleClashes'])
            ->defaults('program', $slug)
            ->name('reports.schedule-clashes');
        Route::get('/reports/{event}/item-schedule', [FestSchoolReportController::class, 'itemSchedule'])
            ->defaults('program', $slug)
            ->name('reports.item-schedule');
        Route::get('/reports/{event}/mark-entry-status', [FestSchoolReportController::class, 'markEntryStatus'])
            ->defaults('program', $slug)
            ->name('reports.mark-entry-status');
        Route::get('/reports/{event}/mark-entry-status/export', [FestSchoolReportController::class, 'exportMarkEntryStatus'])
            ->defaults('program', $slug)
            ->name('reports.mark-entry-status.export');
        Route::get('/reports/{event}/mark-entry-status/pdf', [FestSchoolReportController::class, 'exportMarkEntryStatusPdf'])
            ->defaults('program', $slug)
            ->name('reports.mark-entry-status.pdf');
        Route::get('/reports/{event}/results-summary', [FestSchoolReportController::class, 'resultsSummary'])
            ->defaults('program', $slug)
            ->name('reports.results-summary');
        Route::get('/reports/{event}/published-results', [FestSchoolReportController::class, 'publishedResults'])
            ->defaults('program', $slug)
            ->name('reports.published-results');
        Route::get('/reports/{event}/results-publish-status', [FestSchoolReportController::class, 'resultsPublishStatus'])
            ->defaults('program', $slug)
            ->name('reports.results-publish-status');
        Route::get('/reports/{event}/attendance', [FestSchoolReportController::class, 'attendance'])
            ->defaults('program', $slug)
            ->name('reports.attendance');
        Route::get('/reports/{event}/group-roster', [FestSchoolReportController::class, 'groupRoster'])
            ->defaults('program', $slug)
            ->name('reports.group-roster');
        Route::get('/reports/{event}/attendance-sheet', [FestSchoolReportController::class, 'attendanceSheet'])
            ->defaults('program', $slug)
            ->name('reports.attendance-sheet');
        Route::get('/events/{event}/substitution-requests', [FestSubstitutionRequestController::class, 'index'])
            ->defaults('program', $slug)
            ->name('substitution-requests.index');
        Route::post('/events/{event}/substitution-requests', [FestSubstitutionRequestController::class, 'store'])
            ->defaults('program', $slug)
            ->name('substitution-requests.store');
        Route::get('/events/{event}/clash-requests', [FestClashRequestController::class, 'index'])
            ->defaults('program', $slug)
            ->name('clash-requests.index');
        Route::post('/events/{event}/clash-requests', [FestClashRequestController::class, 'store'])
            ->defaults('program', $slug)
            ->name('clash-requests.store');
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
    Route::get('/{exam}/reports/toppers/export', [\App\Http\Controllers\SchoolAdmin\McqReportController::class, 'exportToppers'])->name('reports.toppers.export');
    Route::get('/{exam}/{tab?}', [McqController::class, 'exam'])->name('exam')->where('tab', 'register|students|hall-tickets|fee|results|toppers|reports|attendance');
    Route::post('/{exam}/attendance', [McqController::class, 'storeAttendance'])->name('attendance.store');
    Route::post('/{exam}/register', [McqController::class, 'register']);
    Route::post('/{exam}/cancel', [McqController::class, 'cancelRegistration'])->name('cancel');
    Route::post('/{exam}/register-by-class', [McqController::class, 'bulkRegister'])->name('register-by-class');
    Route::post('/{exam}/register-bulk', [McqController::class, 'bulkRegister']);
    Route::post('/{exam}/fee', [McqController::class, 'uploadFee']);
    Route::get('/{exam}/hall-tickets/pdf', [McqController::class, 'hallTicketsPdf']);
    Route::get('/{exam}/credentials/export', [McqRegistrationController::class, 'exportCredentials'])->name('credentials.export');
    Route::post('/{exam}/students/{student}/reset-portal-password', [McqRegistrationController::class, 'resetPortalPassword'])->name('students.reset-portal-password');
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
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest', 'english-fest' => 'english-fest', 'science-fest' => 'science-fest', 'custom' => 'custom'];
    if (isset($map[$program])) {
        return redirect("/school-admin/{$tenantId}/{$map[$program]}", 301);
    }

    return redirect("/school-admin/{$tenantId}/fest-programs", 301);
})->where('program', $festProgramSlugs);

Route::get("/programs/{program}/{path}", function (string $tenantId, string $program, string $path) {
    $map = ['kalotsav' => 'kalotsav', 'sports-meet' => 'sports', 'kids-fest' => 'kids-fest', 'teacher-fest' => 'teacher-fest', 'english-fest' => 'english-fest', 'science-fest' => 'science-fest', 'custom' => 'custom'];
    if (isset($map[$program])) {
        return redirect("/school-admin/{$tenantId}/{$map[$program]}/{$path}", 301);
    }

    return redirect("/school-admin/{$tenantId}/fest-programs", 301);
})->where('program', $festProgramSlugs)->where('path', '.*');
