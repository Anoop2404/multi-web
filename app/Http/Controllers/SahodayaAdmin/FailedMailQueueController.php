<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FailedEmailLog;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Http\Request;

class FailedMailQueueController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = FailedEmailLog::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('recipient_email', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $logs = $query->paginate(20)->withQueryString();

        $allQuery = FailedEmailLog::where('sahodaya_id', $this->sahodaya->id);

        return $this->inertia('Admin/Sahodaya/Settings/FailedMails', [
            'logs' => $logs,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'summary' => [
                'pending_count' => (clone $allQuery)->whereIn('status', ['pending', 'retry_failed'])->count(),
                'success_count' => (clone $allQuery)->where('status', 'retry_success')->count(),
                'cancelled_count' => (clone $allQuery)->where('status', 'cancelled')->count(),
                'total_count'   => (clone $allQuery)->count(),
            ],
        ]);
    }

    public function retry(Request $request, string $tenantId, FailedEmailLog $failedMail)
    {
        abort_if($failedMail->sahodaya_id !== $this->sahodaya->id, 403);

        $mailer = SahodayaMailer::for($this->sahodaya->id);
        $ok = $mailer->retryFailedMail($failedMail);

        if ($ok) {
            return back()->with('success', "Email to {$failedMail->recipient_email} sent successfully.");
        }

        return back()->with('error', "Failed to send email to {$failedMail->recipient_email}: {$failedMail->fresh()->error_message}");
    }

    public function bulkRetry(Request $request)
    {
        $ids = $request->input('ids');
        $query = FailedEmailLog::where('sahodaya_id', $this->sahodaya->id)
            ->whereIn('status', ['pending', 'retry_failed']);

        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return back()->with('info', 'No pending failed emails found to retry.');
        }

        $mailer = SahodayaMailer::for($this->sahodaya->id);
        $success = 0;
        $failed = 0;

        foreach ($logs as $log) {
            if ($mailer->retryFailedMail($log)) {
                $success++;
            } else {
                $failed++;
            }
        }

        $msg = "{$success} email(s) sent successfully.";
        if ($failed > 0) {
            $msg .= " {$failed} email(s) failed again (check mail settings).";
        }

        return back()->with($failed > 0 ? 'warning' : 'success', $msg);
    }

    public function cancel(Request $request, string $tenantId, FailedEmailLog $failedMail)
    {
        abort_if($failedMail->sahodaya_id !== $this->sahodaya->id, 403);

        $failedMail->update(['status' => 'cancelled']);

        return back()->with('success', "Queued email cancelled.");
    }

    public function destroy(Request $request, string $tenantId, FailedEmailLog $failedMail)
    {
        abort_if($failedMail->sahodaya_id !== $this->sahodaya->id, 403);

        $failedMail->delete();

        return back()->with('success', "Failed email log entry removed.");
    }
}
