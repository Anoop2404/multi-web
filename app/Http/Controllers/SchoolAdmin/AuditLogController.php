<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\CsvSafety;
use App\Models\DataChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'action'   => 'nullable|string|max:30',
            'log_name' => 'nullable|string|max:50',
            'q'        => 'nullable|string|max:100',
        ]);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $query = $this->filteredQuery($filters);

        $logNameSummary = (clone $query)
            ->selectRaw('log_name, count(*) as total')
            ->groupBy('log_name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'log_name')
            ->all();

        $actionSummary = (clone $query)
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'action')
            ->all();

        $logs = (clone $query)
            ->with('causer:id,name,email')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logs->getCollection()->transform(fn (DataChangeLog $log) => $this->serializeLog($log));

        return $this->inertia('School/Audit/Index', [
            'logs'           => $logs,
            'filters'        => array_merge(['from' => $from, 'to' => $to, 'action' => '', 'log_name' => '', 'q' => ''], $filters),
            'logNameSummary' => $logNameSummary,
            'actionSummary'   => $actionSummary,
            'exportUrl'       => "/school-admin/{$this->school->id}/audit-logs/export?from={$from}&to={$to}",
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'action'   => 'nullable|string|max:30',
            'log_name' => 'nullable|string|max:50',
            'q'        => 'nullable|string|max:100',
        ]);

        $rows = $this->filteredQuery($filters)
            ->with('causer:id,name,email')
            ->latest()
            ->limit(5000)
            ->get();

        $filename = 'school-audit-log-'.$this->school->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['When', 'Scope', 'Action', 'Description', 'Actor', 'Email', 'Subject type', 'Subject ID', 'IP', 'Changes', 'Properties']);

            foreach ($rows as $log) {
                CsvSafety::fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    $log->log_name ? Str::headline(str_replace('_', ' ', $log->log_name)) : 'General',
                    $log->action,
                    $log->description,
                    $log->causer?->name,
                    $log->causer?->email,
                    $log->subject_type,
                    $log->subject_id,
                    $log->ip_address,
                    $log->changes ? json_encode($log->changes) : null,
                    $log->properties ? json_encode($log->properties) : null,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param  array<string, string>  $filters */
    private function filteredQuery(array $filters)
    {
        return DataChangeLog::query()
            ->where('school_id', $this->school->id)
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['log_name'] ?? null, fn ($q, $logName) => $q->where('log_name', $logName))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhere('action', 'like', "%{$term}%")
                        ->orWhere('log_name', 'like', "%{$term}%")
                        ->orWhere('subject_type', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereHas('causer', fn ($user) => $user
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            });
    }

    /** @return array<string, mixed> */
    private function serializeLog(DataChangeLog $log): array
    {
        return [
            'id'             => $log->id,
            'scope_key'      => $log->log_name ?: 'general',
            'scope_label'    => $log->log_name ? Str::headline(str_replace('_', ' ', $log->log_name)) : 'General',
            'origin_label'   => $this->school->name,
            'origin_sub_label' => $this->school->school_prefix ?: null,
            'action'         => $log->action,
            'description'    => $log->description,
            'actor'          => $log->causer?->only('id', 'name', 'email'),
            'actor_email'    => $log->causer?->email,
            'ip_address'     => $log->ip_address,
            'subject_type'   => $log->subject_type,
            'subject_id'     => $log->subject_id,
            'changes'        => $log->changes,
            'properties'     => $log->properties,
            'created_at'     => $log->created_at?->toDateTimeString(),
        ];
    }
}
