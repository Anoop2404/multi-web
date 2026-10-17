<?php

namespace App\Http\Controllers\ExamEngine;

use App\Http\Controllers\ExamEngine\Support\LiveMonitor;
use App\Http\Controllers\ExamEngine\Support\LiveMonitorScope;
use App\Http\Controllers\SahodayaAdmin\SahodayaAdminController;
use App\Models\McqExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sahodaya panel → Talent Search exam → Live monitor (plan §4.5, §5.4): every school,
 * every action. Staff need mcq.view (or mcq.manage) to watch and mcq.manage to act.
 */
class SahodayaLiveMonitorController extends SahodayaAdminController
{
    public function show(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor)
    {
        $scope = $this->scopeFor($request, $exam);
        $base = "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}";

        return $this->inertia('ExamEngine/SahodayaLiveMonitor', $monitor->pageProps($exam, $scope, $monitor->filters($request, $scope), [
            'data'      => "{$base}/live/data",
            'action'    => "{$base}/live/attempts/__REG__/action",
            'passcode'  => "{$base}/live/attempts/__REG__/passcode",
            'broadcast' => "{$base}/live/broadcast",
            'pace'      => "{$base}/live/pace",
            'extend_all' => "{$base}/live/extend-all",
            'hold'      => "{$base}/live/hold",
            'close'     => "{$base}/live/close",
            'paper'     => "{$base}/question-banks",
            'session'   => "{$base}/session",
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

    public function broadcast(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->broadcast($request, $exam, $this->scopeFor($request, $exam, acting: true));
    }

    public function pace(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->pace($request, $exam, $this->scopeFor($request, $exam, acting: true));
    }

    public function extendAll(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->extendAll($request, $exam, $this->scopeFor($request, $exam, acting: true));
    }

    public function hold(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->hold($request, $exam, $this->scopeFor($request, $exam, acting: true));
    }

    public function close(Request $request, string $tenantId, McqExam $exam, LiveMonitor $monitor): JsonResponse
    {
        return $monitor->close($request, $exam, $this->scopeFor($request, $exam, acting: true));
    }

    private function scopeFor(Request $request, McqExam $exam, bool $acting = false): LiveMonitorScope
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $user = $request->user();
        if ($this->isStaff) {
            abort_unless($user?->can('mcq.view') || $user?->can('mcq.manage'), 403, 'You do not have access to Talent Search exams.');
        }
        if ($acting) {
            $this->assertStaffCan('mcq.manage');
        }

        $canAct = ! $this->isStaff || (bool) $user?->can('mcq.manage');

        return new LiveMonitorScope(
            via: 'sahodaya',
            actor: LiveMonitor::actorFor($user, ['sahodaya_admin', 'exam_controller', 'sahodaya_staff']),
            attemptActions: $canAct ? LiveMonitor::ATTEMPT_ACTIONS : [],
            runActions: $canAct,
        );
    }
}
