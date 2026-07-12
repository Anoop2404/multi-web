<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\User;

class NotificationLogWriter
{
    public function queued(?User $user, string $subject, ?string $templateKey = null, ?string $to = null, ?string $body = null): NotificationLog
    {
        return $this->write('queued', $user, $subject, $templateKey, $to, $body);
    }

    public function sent(NotificationLog $log): void
    {
        $log->update([
            'status'   => 'sent',
            'sent_at'  => now(),
            'attempts' => $log->attempts + 1,
            'error'    => null,
        ]);
    }

    public function failed(NotificationLog $log, string $error): void
    {
        $log->update([
            'status'   => 'failed',
            'attempts' => $log->attempts + 1,
            'error'    => mb_substr($error, 0, 2000),
        ]);
    }

    public function skipped(?User $user, string $subject, string $reason, ?string $templateKey = null, ?string $body = null): NotificationLog
    {
        return NotificationLog::create([
            'template_key'    => $templateKey,
            'notifiable_type' => $user ? $user->getMorphClass() : null,
            'notifiable_id'   => $user?->id,
            'to'              => $user?->email,
            'subject'         => $subject,
            'body'            => $body,
            'status'          => 'skipped',
            'error'           => mb_substr($reason, 0, 500),
        ]);
    }

    private function write(string $status, ?User $user, string $subject, ?string $templateKey, ?string $to, ?string $body = null): NotificationLog
    {
        return NotificationLog::create([
            'template_key'    => $templateKey,
            'notifiable_type' => $user ? $user->getMorphClass() : null,
            'notifiable_id'   => $user?->id,
            'to'              => $to ?? $user?->email,
            'subject'         => $subject,
            'body'            => $body,
            'status'          => $status,
        ]);
    }
}
