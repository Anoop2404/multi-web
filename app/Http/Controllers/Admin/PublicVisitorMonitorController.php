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
        return Inertia::render('PublicVisitors', ['monitor' => $this->snapshot()]);
    }

    public function data()
    {
        return response()->json($this->snapshot())->header('Cache-Control', 'private, no-store');
    }

    private function snapshot(): array
    {
        $snapshot = PublicEventVisitorMonitor::snapshot();

        $eventTenantIds = array_column($snapshot['events'], 'tenant_id');
        $sahodayaTenantIds = array_keys($snapshot['sahodayas'] ?? []);
        $allTenantIds = array_values(array_unique(array_filter(array_merge($eventTenantIds, $sahodayaTenantIds))));

        $tenants = Tenant::whereIn('id', $allTenantIds)
            ->get(['id', 'name', 'subdomain'])
            ->keyBy('id');

        // Build sorted sahodaya-wise breakdown list
        $sahodayaRows = [];
        foreach (($snapshot['sahodayas'] ?? []) as $tenantId => $counts) {
            $tenant = $tenants->get($tenantId);
            $publicCount = (int) ($counts['public'] ?? 0);
            $tvCount = (int) ($counts['tv'] ?? 0);
            $sahodayaRows[] = [
                'tenant_id'       => $tenantId,
                'tenant_name'     => $tenant?->name ?? 'Unknown Sahodaya',
                'subdomain'       => $tenant?->subdomain,
                'public_visitors' => $publicCount,
                'tv_screens'      => $tvCount,
                'total'           => $publicCount + $tvCount,
            ];
        }
        usort($sahodayaRows, fn ($a, $b) => $b['total'] <=> $a['total']);
        $snapshot['sahodayas'] = $sahodayaRows;

        // Enrich event names & tenant names
        $eventIds = array_column($snapshot['events'], 'event_id');
        $eventTitles = \App\Models\FestEvent::whereIn('id', $eventIds)->pluck('title', 'id');

        $snapshot['events'] = array_map(function ($row) use ($tenants, $eventTitles) {
            $tenant = $tenants->get($row['tenant_id']);
            return $row + [
                'tenant_name' => $tenant?->name ?? $row['tenant_id'],
                'event_title' => $eventTitles[$row['event_id']] ?? ('Event #'.$row['event_id']),
            ];
        }, $snapshot['events']);

        return $snapshot;
    }
}
