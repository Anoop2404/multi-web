<?php

namespace App\Http\Controllers\Admin;

use App\Support\CsvSafety;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\AuditLogCatalog;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Arr;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'category'  => $request->query('category', ''),
            'action'    => $request->query('action', ''),
            'from'      => $request->query('from', ''),
            'to'        => $request->query('to', ''),
            'q'         => trim((string) $request->query('q', '')),
            'tenant_id' => $request->query('tenant_id', ''),
        ];

        $query = $this->filteredQuery($filters);

        $summary = (clone $query)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $actionSummary = (clone $query)
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(12)
            ->pluck('total', 'action')
            ->all();

        $logs = (clone $query)
            ->with('user:id,name,email')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $tenantIds = collect($logs->items())
            ->map(fn (AuditLog $log) => Arr::get($log->properties, 'tenant_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $tenantNames = Tenant::whereIn('id', $tenantIds)->pluck('name', 'id')->all();

        $logs->getCollection()->transform(fn (AuditLog $log) => $this->serializeLog($log, $tenantNames));

        return inertia('Audit/Index', [
            'logs'          => $logs,
            'summary'       => $summary,
            'actionSummary' => $actionSummary,
            'filters'       => $filters,
            'categories'    => AuditLogCatalog::categories(),
            'tenants'       => Tenant::where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']),
            'total'         => (clone $query)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'category'  => $request->query('category', ''),
            'action'    => $request->query('action', ''),
            'from'      => $request->query('from', ''),
            'to'        => $request->query('to', ''),
            'q'         => trim((string) $request->query('q', '')),
            'tenant_id' => $request->query('tenant_id', ''),
        ];

        $rows = $this->filteredQuery($filters)
            ->with('user:id,name,email')
            ->latest()
            ->limit(5000)
            ->get();

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['When', 'Category', 'Action', 'Description', 'User', 'Email', 'IP', 'Subject type', 'Subject ID', 'Properties']);

            foreach ($rows as $log) {
                CsvSafety::fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    AuditLogCatalog::label($log->category ?? 'system'),
                    $log->action,
                    $log->description,
                    $log->user?->name,
                    $log->user?->email ?? ($log->properties['email'] ?? null),
                    $log->ip_address,
                    $log->subject_type,
                    $log->subject_id,
                    $log->properties ? json_encode($log->properties) : null,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param  array<string, string>  $filters */
    private function filteredQuery(array $filters)
    {
        return AuditLog::query()
            ->when($filters['category'], fn ($q, $cat) => $q->where('category', $cat))
            ->when($filters['tenant_id'], fn ($q, $tenantId) => $q->where('properties->tenant_id', $tenantId))
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'], function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhere('action', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('email', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%"));
                });
            });
    }

    /** @return array<string, mixed> */
    private function serializeLog(AuditLog $log, array $tenantNames = []): array
    {
        $originId = $log->properties['tenant_id'] ?? $log->properties['school_id'] ?? null;

        return [
            'id'            => $log->id,
            'scope_key'     => $log->category ?? AuditLogCatalog::categoryForAction($log->action),
            'scope_label'   => AuditLogCatalog::label($log->category ?? AuditLogCatalog::categoryForAction($log->action)),
            'origin_label'  => $originId ? ($tenantNames[$originId] ?? $originId) : '—',
            'origin_sub_label' => $log->properties['page'] ?? null,
            'action'        => $log->action,
            'description'   => $log->description,
            'actor'         => $log->user?->only('id', 'name', 'email'),
            'actor_email'   => $log->user?->email ?? ($log->properties['email'] ?? null),
            'ip_address'    => $log->ip_address,
            'subject_type'  => $log->subject_type,
            'subject_id'    => $log->subject_id,
            'properties'    => $log->properties,
            'changes'       => null,
            'created_at'    => $log->created_at?->toDateTimeString(),
        ];
    }
}
