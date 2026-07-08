<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class EmailDeliveryReportController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'all';

        $logs = NotificationLog::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'failed'  => NotificationLog::where('status', 'failed')->count(),
            'queued'  => NotificationLog::where('status', 'queued')->count(),
            'sent'    => NotificationLog::where('status', 'sent')->count(),
            'skipped' => NotificationLog::where('status', 'skipped')->count(),
        ];

        return $this->inertia('Sahodaya/Finance/EmailDelivery', [
            'logs'    => $logs,
            'summary' => $summary,
            'filters' => ['status' => $status],
        ]);
    }

    public function retry(string $tenantId, NotificationLog $notificationLog, NotificationService $notifications)
    {
        abort_unless(in_array($notificationLog->status, ['failed', 'skipped'], true), 422);

        $user = User::where('email', $notificationLog->to)->first();
        if (! $user) {
            return back()->with('error', 'Recipient user not found for retry.');
        }

        $notifications->notifyEmailOnly(
            $user,
            $notificationLog->subject ?? 'Notification',
            'This is a resent notification from the delivery log.',
            $notificationLog->template_key,
        );

        return back()->with('success', 'Retry queued for '.$notificationLog->to);
    }
}
