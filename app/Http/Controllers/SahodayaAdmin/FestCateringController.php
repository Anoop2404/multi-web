<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestCateringController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $orders = FestCateringOrder::where('event_id', $event->id)
            ->orderBy('meal_date')
            ->orderBy('school_id')
            ->get();

        $schools = Tenant::whereIn('id', $orders->pluck('school_id')->unique())
            ->pluck('name', 'id');

        $summary = [
            'total_heads' => $orders->where('status', '!=', 'cancelled')->sum('head_count'),
            'confirmed'   => $orders->where('status', 'confirmed')->count(),
            'requested'   => $orders->where('status', 'requested')->count(),
        ];

        return $this->inertia('Sahodaya/Events/Catering', $this->withEventActivity($event, FestPageActivity::CATERING, [
            'event'   => $event,
            'orders'  => $orders,
            'schools' => $schools,
            'summary' => $summary,
        ]));
    }

    public function updateStatus(Request $request, string $tenantId, FestEvent $event, FestCateringOrder $order, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($order->event_id !== $event->id, 403);

        $data = $request->validate([
            'status' => 'required|in:requested,confirmed,cancelled',
        ]);

        $order->update(['status' => $data['status']]);

        $audit->festEvent($event, FestPageActivity::CATERING, 'fest.catering.status_updated', "Catering order {$data['status']}", [
            'order_id' => $order->id,
            'status'   => $data['status'],
        ]);

        return back()->with('success', 'Order updated.');
    }
}
