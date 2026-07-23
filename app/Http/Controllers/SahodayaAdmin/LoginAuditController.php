<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LoginAuditController extends SahodayaAdminController
{
    /**
     * Login events don't carry a tenant_id/school_id column — they're logged (see
     * AuthController::auditContext()) with the HOST tenant the login was attempted against,
     * stored in properties->tenant_id / properties->tenant_type, since auth happens before
     * any Sahodaya-scoped route/middleware runs. Scope to logins made either directly on this
     * Sahodaya's own portal, or on any of its schools' portals — without this, every Sahodaya
     * admin's "Login audit" page showed every OTHER Sahodaya's login events too.
     */
    private function scopeToThisSahodaya(Builder $query): Builder
    {
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        return $query->where(function ($q) use ($schoolIds) {
            $q->where('properties->tenant_id', $this->sahodaya->id)
                ->orWhereIn('properties->tenant_id', $schoolIds);
        });
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
            // 'login' (not 'login.success') is the actual stored action for a successful
            // login — see LogAuthEventJob::descriptionFor()/PlatformAuditLogger::login().
            // 'login.success' never matched any row; kept as an accepted input value below
            // for backwards compatibility with any bookmarked/shared URLs, but mapped to
            // 'login' before it ever reaches the query.
            'action' => 'nullable|in:all,login.failed,login,login.success',
        ]);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $action = $filters['action'] ?? 'all';
        $queryAction = $action === 'login.success' ? 'login' : $action;

        $logs = $this->scopeToThisSahodaya(AuditLog::query())
            ->where('category', 'auth')
            ->when($queryAction !== 'all', fn ($q) => $q->where('action', $queryAction))
            ->when($queryAction === 'all', fn ($q) => $q->whereIn('action', ['login.failed', 'login']))
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'failed'  => $this->scopeToThisSahodaya(AuditLog::where('action', 'login.failed'))
                ->whereBetween('created_at', [$from, $to.' 23:59:59'])->count(),
            'success' => $this->scopeToThisSahodaya(AuditLog::where('action', 'login'))
                ->whereBetween('created_at', [$from, $to.' 23:59:59'])->count(),
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

        $logs = $this->scopeToThisSahodaya(AuditLog::query())
            ->where('category', 'auth')
            ->whereIn('action', ['login.failed', 'login'])
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
