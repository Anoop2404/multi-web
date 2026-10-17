<?php

namespace App\Http\Controllers\ExamEngine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExamEngine\Support\LiveMonitor;
use App\Http\Controllers\ExamEngine\Support\LiveMonitorScope;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exam portal → Live monitor. Exam controllers and Sahodaya admins run the exam (every action);
 * exam-day staff assigned to the exam watch it read-only (plan §5.4).
 */
class ExamPortalLiveMonitorController extends Controller
{
    private const FULL_ROLES = ['exam_controller', 'sahodaya_admin'];

    public function show(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor)
    {
        $scope = $this->scopeFor($request, $tenantId, $exam);
        $base = "/portal/exam/{$tenantId}/exams/{$exam->id}";

        return inertia('ExamEngine/ExamPortalLiveMonitor', array_merge(
            $monitor->pageProps($exam, $scope, $monitor->filters($request, $scope), [
                'data'      => "{$base}/live/data",
                'action'    => "{$base}/live/attempts/__REG__/action",
                'passcode'  => "{$base}/live/attempts/__REG__/passcode",
                'broadcast' => "{$base}/live/broadcast",
                'pace'      => "{$base}/live/pace",
            'extend_all' => "{$base}/live/extend-all",
            'hold'      => "{$base}/live/hold",
                'close'     => "{$base}/live/close",
                'paper'     => null,
                'session'   => "{$base}/supervision",
            ]),
            ['sahodaya' => Tenant::findOrFail($tenantId)->only('id', 'name')],
        ));
    }

    public function data(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $tenantId, $exam);

        return response()->json($monitor->snapshot($exam, $scope, $monitor->filters($request, $scope)));
    }

    public function action(Request $request, string $tenantId, McqExam $exam, int $regId, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $tenantId, $exam, acting: true);

        return $monitor->attemptAction($request, $exam, $monitor->registrationFor($exam, $regId, $scope), $scope);
    }

    public function passcode(Request $request, string $tenantId, McqExam $exam, int $regId, LiveMonitor $monitor): JsonResponse
    {
        $scope = $this->scopeFor($request, $tenantId, $exam, acting: true);

        return $monitor->reissuePasscode($exam, $monitor->registrationFor($exam, $regId, $scope), $scope);
    }

    public function broadcast(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->broadcast($request, $exam, $this->scopeFor($request, $tenantId, $exam, acting: true));
    }

    public function pace(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->pace($request, $exam, $this->scopeFor($request, $tenantId, $exam, acting: true));
    }

    public function extendAll(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->extendAll($request, $exam, $this->scopeFor($request, $tenantId, $exam, acting: true));
    }

    public function hold(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->hold($request, $exam, $this->scopeFor($request, $tenantId, $exam, acting: true));
    }

    public function close(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->close($request, $exam, $this->scopeFor($request, $tenantId, $exam, acting: true));
    }

    private function scopeFor(Request $request, string $tenantId, McqExam $exam, bool $acting = false): LiveMonitorScope
    {
        abort_if($exam->tenant_id !== $tenantId, 403);

        $user = $request->user();
        // EnsureExamPortal already ties the route tenant to the user; repeated here so a role held
        // in one Sahodaya can never grant access to another's exam if the routes ever move.
        abort_unless($user->isSuperAdmin() || (string) $user->tenant_id === (string) $tenantId, 403);
        $full = $user->isSuperAdmin() || $user->hasAnyRole(self::FULL_ROLES);

        if (! $full) {
            $assigned = McqExamStaff::where('exam_id', $exam->id)->where('user_id', $user->id)->exists();
            abort_unless($assigned, 403, 'You\'re not assigned as staff for this exam.');
        }
        abort_if($acting && ! $full, 403, 'Exam staff can watch the live monitor but only exam controllers can act on it.');

        return new LiveMonitorScope(
            via: 'exam_portal',
            actor: LiveMonitor::actorFor($user, ['exam_controller', 'sahodaya_admin', 'exam_staff']),
            attemptActions: $full ? LiveMonitor::ATTEMPT_ACTIONS : [],
            runActions: $full,
        );
    }
}
