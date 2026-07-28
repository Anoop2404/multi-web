<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\AuditLogCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'category'  => 'nullable|string|max:30',
            'action'    => 'nullable|string|max:50',
            'school_id' => 'nullable|string',
            'q'         => 'nullable|string|max:100',
        ]);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

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
            ->limit(10)
            ->pluck('total', 'action')
            ->all();

        $logs = (clone $query)
            ->with('user:id,name,email')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $tenantIds = collect($logs->items())
            ->map(fn (AuditLog $log) => Arr::get($log->properties, 'tenant_id') ?? Arr::get($log->properties, 'school_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $tenantNames = Tenant::whereIn('id', $tenantIds)->pluck('name', 'id')->all();
        $schoolNames = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('name', 'id')
            ->all();

        $logs->getCollection()->transform(fn (AuditLog $log) => $this->serializeLog($log, $tenantNames, $schoolNames));

        return $this->inertia('Sahodaya/Audit/Index', [
            'logs'         => $logs,
            'summary'      => $summary,
            'actionSummary'=> $actionSummary,
            'filters'      => array_merge(['from' => $from, 'to' => $to, 'category' => '', 'action' => '', 'school_id' => '', 'q' => ''], $filters),
            'categories'   => AuditLogCatalog::categories(),
            'schools'      => Tenant::query()
                ->where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->orderBy('name')
                ->get(['id', 'name']),
            'exportUrl'    => "/sahodaya-admin/{$this->sahodaya->id}/audit-logs/export?from={$from}&to={$to}",
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'category'  => 'nullable|string|max:30',
            'action'    => 'nullable|string|max:50',
            'school_id' => 'nullable|string',
            'q'         => 'nullable|string|max:100',
        ]);

        $logs = $this->filteredQuery($filters)
            ->with('user:id,name,email')
            ->latest()
            ->limit(5000)
            ->get();

        $filename = 'sahodaya-audit-log-'.$this->sahodaya->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Category', 'Action', 'Description', 'Actor', 'Email', 'Origin ID', 'Subject type', 'Subject ID', 'IP', 'Properties']);

            foreach ($logs as $log) {
                $originId = $log->properties['tenant_id'] ?? $log->properties['school_id'] ?? null;
                fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    AuditLogCatalog::label($log->category ?? 'system'),
                    $log->action,
                    $log->description,
                    $log->user?->name,
                    $log->user?->email ?? ($log->properties['email'] ?? null),
                    $originId,
                    $log->subject_type,
                    $log->subject_id,
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
        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        return AuditLog::query()
            ->where(function ($query) use ($schoolIds) {
                $query->where('properties->tenant_id', $this->sahodaya->id)
                    ->orWhere('properties->school_id', $this->sahodaya->id);

                foreach ($schoolIds as $schoolId) {
                    $query->orWhere('properties->tenant_id', $schoolId)
                        ->orWhere('properties->school_id', $schoolId);
                }
            })
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['school_id'] ?? null, function ($q, $schoolId) {
                $q->where(function ($inner) use ($schoolId) {
                    $inner->where('properties->tenant_id', $schoolId)
                        ->orWhere('properties->school_id', $schoolId);
                });
            })
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhere('action', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            });
    }

    /** @return array<string, mixed> */
    private function serializeLog(AuditLog $log, array $tenantNames = [], array $schoolNames = []): array
    {
        $originId = $log->properties['tenant_id'] ?? $log->properties['school_id'] ?? null;
        $originLabel = $originId
            ? ($schoolNames[$originId] ?? $tenantNames[$originId] ?? $originId)
            : '—';

        return [
            'id'              => $log->id,
            'scope_key'       => $log->category ?? AuditLogCatalog::categoryForAction($log->action),
            'scope_label'     => AuditLogCatalog::label($log->category ?? AuditLogCatalog::categoryForAction($log->action)),
            'origin_label'    => $originLabel,
            'origin_sub_label'=> $log->properties['page'] ?? null,
            'action'          => $log->action,
            'description'     => $log->description,
            'actor'           => $log->user?->only('id', 'name', 'email'),
            'actor_email'     => $log->user?->email ?? ($log->properties['email'] ?? null),
            'ip_address'      => $log->ip_address,
            'subject_type'    => $log->subject_type,
            'subject_id'      => $log->subject_id,
            'changes'         => null,
            'properties'      => $log->properties,
            'created_at'      => $log->created_at?->toDateTimeString(),
        ];
    }
}
