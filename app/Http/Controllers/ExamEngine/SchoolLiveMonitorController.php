<?php

namespace App\Http\Controllers\ExamEngine;

use App\Http\Controllers\ExamEngine\Support\LiveMonitor;
use App\Http\Controllers\ExamEngine\Support\LiveMonitorScope;
use App\Http\Controllers\SchoolAdmin\SchoolAdminController;
use App\Models\McqExam;
use App\Services\ExamEngine\ExamEngineRun;
use App\Support\TenantUserCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * School panel → Talent Search exam → Live monitor: the school MCQ coordinator's first-line
 * support desk (plan §5.4, decision 8). Only this school's candidates, ever: the engine is
 * always asked for this school, every returned row is re-checked, and every action target is
 * checked before the engine is called. Actions: reissue passcode, allow device switch, unlock,
 * and extend up to the exam's coordinator cap per candidate. No force submit, broadcast or close.
 */
class SchoolLiveMonitorController extends SchoolAdminController
{
    private const COORDINATOR_ROLES = ['school_mcq_coordinator', 'school_event_coordinator'];

    public function show(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor)
    {
        $scope = $this->scopeFor($request, $exam);
        $base = "/school-admin/{$this->school->id}/mcq/{$exam->id}";

        return $this->inertia('ExamEngine/SchoolLiveMonitor', $monitor->pageProps($exam, $scope, $monitor->filters($request, $scope), [
            'data'      => "{$base}/live/data",
            'action'    => "{$base}/live/attempts/__REG__/action",
            'passcode'  => "{$base}/live/attempts/__REG__/passcode",
            'broadcast' => null,
            'pace'      => null,
            'close'     => null,
            'paper'     => null,
            'session'   => null,
        ]));
    }

    public function data(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $exam);

        return response()->json($monitor->snapshot($exam, $scope, $monitor->filters($request, $scope)));
    }

    public function action(Request $request, string $tenantId, McqExam $exam, int $regId, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $exam, acting: true);

        return $monitor->attemptAction($request, $exam, $monitor->registrationFor($exam, $regId, $scope), $scope);
    }

    public function passcode(Request $request, string $tenantId, McqExam $exam, int $regId, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $exam, acting: true);

        return $monitor->reissuePasscode($exam, $monitor->registrationFor($exam, $regId, $scope), $scope);
    }

    private function scopeFor(Request $request, McqExam $exam, bool $acting = false): LiveMonitorScope
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $user = $request->user();
        $privileged = $user->isSuperAdmin()
            || $user->hasAnyRole(array_merge(TenantUserCatalog::schoolManagementRoles(), ['sahodaya_admin']));

        if (! $privileged) {
            abort_unless($user->hasAnyRole(self::COORDINATOR_ROLES), 403, 'The live monitor is for the school MCQ coordinator and school admins.');
            abort_unless($user->can('mcq.view') || $user->can('mcq.manage'), 403, 'You do not have access to Talent Search exams.');
        }

        $canAct = $privileged || $user->can('mcq.manage');
        abort_if($acting && ! $canAct, 403, 'View-only access. Contact your school administrator.');

        return new LiveMonitorScope(
            via: 'school',
            actor: LiveMonitor::actorFor($user, ['school_mcq_coordinator', 'school_admin', 'school_principal', 'school_vice_principal', 'school_event_coordinator', 'sahodaya_admin']),
            attemptActions: $canAct ? LiveMonitor::COORDINATOR_ACTIONS : [],
            runActions: false,
            schoolId: (string) $this->school->id,
            extendCap: max(0, (int) (ExamEngineRun::settings($exam)['coordinator_extend_cap_minutes'] ?? 0)),
        );
    }
}
