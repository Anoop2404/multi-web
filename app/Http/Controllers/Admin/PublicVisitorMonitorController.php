<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Events\PublicEventVisitorMonitor;
use Inertia\Inertia;

class PublicVisitorMonitorController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/PublicVisitors', ['monitor' => $this->snapshot()]);
    }

    public function data()
    {
        return response()->json($this->snapshot())->header('Cache-Control', 'private, no-store');
    }

    private function snapshot(): array
    {
        $snapshot = PublicEventVisitorMonitor::snapshot();
        $names = Tenant::whereIn('id', array_column($snapshot['events'], 'tenant_id'))->pluck('name', 'id');
        $snapshot['events'] = array_map(fn ($row) => $row + ['tenant_name' => $names[$row['tenant_id']] ?? $row['tenant_id']], $snapshot['events']);

        return $snapshot;
    }
}
