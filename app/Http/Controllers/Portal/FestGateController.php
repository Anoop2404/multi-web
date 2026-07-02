<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\Tenant;
use App\Services\Events\FestQrVerificationService;
use Illuminate\Http\Request;

class FestGateController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();

        $events = FestEvent::where('tenant_id', $tenantId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'event_start']);

        return inertia('Portal/FestOps/GateCheck', [
            'sahodaya' => $sahodaya->only('id', 'name'),
            'events'   => $events,
            'selectedEventId' => $request->integer('event_id') ?: null,
            'lastScan' => session('gate_scan'),
        ]);
    }

    public function verify(Request $request, string $tenantId, FestEvent $event, FestQrVerificationService $service)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->assertCanScan($request, $event);

        $data = $request->validate([
            'payload' => 'required|string|max:500',
            'mark_attendance' => 'nullable|boolean',
        ]);

        $result = $service->verify(
            $event,
            $data['payload'],
            (bool) ($data['mark_attendance'] ?? false),
            $request->user()->id,
        );

        return back()->with('gate_scan', $result);
    }

    public function verifyJson(Request $request, string $tenantId, FestEvent $event, FestQrVerificationService $service)
    {
        abort_if($event->tenant_id !== $tenantId, 403);
        $this->assertCanScan($request, $event);

        $data = $request->validate([
            'payload' => 'required|string|max:500',
            'mark_attendance' => 'nullable|boolean',
        ]);

        return response()->json($service->verify(
            $event,
            $data['payload'],
            (bool) ($data['mark_attendance'] ?? false),
            $request->user()->id,
        ));
    }

    private function assertCanScan(Request $request, FestEvent $event): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->hasAnyRole(['sahodaya_admin', 'fest_event_ops'])) {
            return;
        }

        $assigned = FestEventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to scan for this event.');
    }
}
