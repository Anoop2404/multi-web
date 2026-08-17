<?php

use App\Http\Controllers\Admin\StateAdminDashboardController;
use App\Http\Controllers\StateAdmin\StateBoardResultsController;
use App\Http\Controllers\StateAdmin\StateFestWorkspaceController;
use App\Http\Controllers\StateAdmin\StateAttendanceController;
use App\Http\Controllers\StateAdmin\StateQualifierReviewController;
use Illuminate\Support\Facades\Route;

/**
 * Dedicated State-domain routes (docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md §26.3,
 * WP-01 "Add State domain configuration, domain routes..."). This is additive, not a
 * replacement: the existing /admin/state-workspace/* routes in routes/web.php keep
 * working unchanged. `state.domain` defaults to 'state.localhost' (config/state.php),
 * which nothing in production resolves to, so this group is inert until STATE_APP_DOMAIN
 * is deliberately configured and DNS-pointed here — that's a Phase 9 rollout action, not
 * something this file forces. Route names are prefixed `state.portal.*` (distinct from
 * the existing `admin.state.*` names) so both can be registered at boot without a name
 * collision.
 *
 * Deliberately NOT included here: state-programs (FestStateProgram — central data) and
 * state-remittances (StateRemittance — uses CentralConnection). Per the master plan's
 * §3.1 system-boundaries table, only State *operational* data (qualifier intake,
 * scrutiny, State registrations/conduct) belongs on the dedicated State connection/
 * domain; program master and remittance records stay on the central admin domain.
 *
 * Still open, intentionally not solved here: STATE_SESSION_COOKIE (config/state.php)
 * implies a session cookie scoped to the State domain, but this group still uses the
 * default 'auth' guard/session middleware. Reusing the same session across a genuinely
 * separate domain won't work in a browser (cookies don't cross domains) — sorting that
 * out is part of the actual domain cutover, not this route-registration step.
 */
Route::domain(config('state.domain'))
    ->middleware(['web', 'auth', 'password.change', 'state.admin', 'state.domain'])
    ->name('state.portal.')
    ->group(function () {
        Route::get('/', [StateAdminDashboardController::class, 'index'])->name('dashboard');

        Route::prefix('qualifiers')->name('qualifiers.')->group(function () {
            Route::get('/', [StateQualifierReviewController::class, 'index'])->name('index');
            Route::post('/intake', [StateQualifierReviewController::class, 'storeIntake'])->name('store-intake');
            Route::get('/{intake}', [StateQualifierReviewController::class, 'show'])->name('show');
            Route::post('/{intake}/approve', [StateQualifierReviewController::class, 'approve'])->name('approve');
            Route::post('/{intake}/entries', [StateQualifierReviewController::class, 'storeEntry'])->name('entries.store');
            Route::put('/{intake}/entries/{entry}', [StateQualifierReviewController::class, 'updateEntry'])->name('entries.update');
            Route::delete('/{intake}/entries/{entry}', [StateQualifierReviewController::class, 'destroyEntry'])->name('entries.destroy');
            Route::post('/{intake}/entries/{entry}/review', [StateQualifierReviewController::class, 'reviewEntry'])->name('entries.review');
        });

        Route::prefix('fest')->name('fest.')->group(function () {
            Route::get('/', [StateFestWorkspaceController::class, 'index'])->name('index');
            Route::post('/', [StateFestWorkspaceController::class, 'store'])->name('store');
            Route::get('/{event}', [StateFestWorkspaceController::class, 'show'])->name('show');
            Route::post('/{event}/assign-chest-numbers', [StateFestWorkspaceController::class, 'assignChestNumbers'])->name('assign-chest-numbers');
            Route::get('/{event}/attendance', [StateAttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/{event}/attendance', [StateAttendanceController::class, 'store'])->name('attendance.store');
            Route::post('/{event}/judges', [StateFestWorkspaceController::class, 'assignJudge'])->name('judges.assign');
            Route::delete('/{event}/judges/{assignment}', [StateFestWorkspaceController::class, 'unassignJudge'])->name('judges.unassign');
            Route::post('/{event}/marks', [StateFestWorkspaceController::class, 'enterMark'])->name('marks.enter');
            Route::post('/{event}/publish-results', [StateFestWorkspaceController::class, 'publishResults'])->name('results.publish');
        });

        Route::get('/board-results', [StateBoardResultsController::class, 'index'])->name('board-results');
    });
