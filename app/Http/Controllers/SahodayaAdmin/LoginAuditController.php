<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class LoginAuditController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
            'action' => 'nullable|in:all,login.failed,login.success',
        ]);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $action = $filters['action'] ?? 'all';

        $logs = AuditLog::query()
            ->where('category', 'auth')
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($action === 'all', fn ($q) => $q->whereIn('action', ['login.failed', 'login.success']))
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'failed'  => AuditLog::where('action', 'login.failed')->whereBetween('created_at', [$from, $to.' 23:59:59'])->count(),
            'success' => AuditLog::where('action', 'login.success')->whereBetween('created_at', [$from, $to.' 23:59:59'])->count(),
        ];

        return $this->inertia('Sahodaya/Auth/LoginAudit', [
            'logs'    => $logs,
            'summary' => $summary,
            'filters' => array_merge(['from' => $from, 'to' => $to, 'action' => $action], $filters),
            'exportUrl' => "/sahodaya-admin/{$this->sahodaya->id}/auth/login-audit/export?from={$from}&to={$to}&action={$action}",
        ]);
    }

    public function export(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?? now()->subDays(30)->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        $logs = AuditLog::query()
            ->where('category', 'auth')
            ->whereIn('action', ['login.failed', 'login.success'])
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();

        $filename = 'login-audit-'.$from.'-'.$to.'.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Action', 'Username', 'IP', 'Description', 'Date']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->action,
                    $log->properties['username'] ?? $log->properties['email'] ?? '',
                    $log->ip_address,
                    $log->description,
                    $log->created_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
