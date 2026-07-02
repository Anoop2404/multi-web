<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditLogCatalog;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'category' => $request->query('category', ''),
            'action'   => $request->query('action', ''),
            'from'     => $request->query('from', ''),
            'to'       => $request->query('to', ''),
            'q'        => trim((string) $request->query('q', '')),
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
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => $this->serializeLog($log));

        return inertia('Audit/Index', [
            'logs'          => $logs,
            'summary'       => $summary,
            'actionSummary' => $actionSummary,
            'filters'       => $filters,
            'categories'    => AuditLogCatalog::categories(),
            'total'         => (clone $query)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'category' => $request->query('category', ''),
            'action'   => $request->query('action', ''),
            'from'     => $request->query('from', ''),
            'to'       => $request->query('to', ''),
            'q'        => trim((string) $request->query('q', '')),
        ];

        $rows = $this->filteredQuery($filters)
            ->with('user:id,name,email')
            ->latest()
            ->limit(5000)
            ->get();

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Category', 'Action', 'Description', 'User', 'Email', 'IP', 'Properties']);

            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    AuditLogCatalog::label($log->category ?? 'system'),
                    $log->action,
                    $log->description,
                    $log->user?->name,
                    $log->user?->email ?? ($log->properties['email'] ?? null),
                    $log->ip_address,
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
    private function serializeLog(AuditLog $log): array
    {
        return [
            'id'          => $log->id,
            'category'    => $log->category ?? AuditLogCatalog::categoryForAction($log->action),
            'category_label' => AuditLogCatalog::label($log->category ?? AuditLogCatalog::categoryForAction($log->action)),
            'action'      => $log->action,
            'description' => $log->description,
            'user'        => $log->user?->only('id', 'name', 'email'),
            'email'       => $log->user?->email ?? ($log->properties['email'] ?? null),
            'ip_address'  => $log->ip_address,
            'properties'  => $log->properties,
            'created_at'  => $log->created_at?->toDateTimeString(),
        ];
    }
}
