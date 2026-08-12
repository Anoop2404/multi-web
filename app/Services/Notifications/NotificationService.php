<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private NotificationLogWriter $logWriter,
    ) {}

    /**
     * Deliver notifications by channel. FCM push is sent only when `in_app` is included.
     */
    public function notify(User $user, string $title, string $body, ?string $actionUrl = null, array $channels = ['in_app'], ?string $templateKey = null): ?InAppNotification
    {
        $notification = null;

        if (in_array('in_app', $channels, true)) {
            $notification = InAppNotification::create([
                'user_id'    => $user->id,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $actionUrl,
            ]);

            if (config('erp.fcm_in_app_only', true)) {
                app(FcmPushService::class)->sendToUser($user, $title, $body, $actionUrl);
            }
        }

        if (in_array('email', $channels, true)) {
            $this->sendEmail($user, $title, $body, $templateKey);
        }

        return $notification;
    }

    public function notifyEmailOnly(User $user, string $title, string $body, ?string $templateKey = null): void
    {
        $this->sendEmail($user, $title, $body, $templateKey);
    }

    public function notifyFromTemplate(User $user, string $slug, array $replacements = [], ?string $actionUrl = null): ?InAppNotification
    {
        $template = NotificationTemplate::where('slug', $slug)->where('is_active', true)->first();

        $title = $template?->title;
        $body = $template?->body_template;

        if (! $template) {
            $fallback = $this->fallbackTemplate($slug);
            if ($fallback) {
                $title = $fallback['title'];
                $body = $fallback['body_template'];
            } else {
                Log::warning("Notification template missing: {$slug}");

                return null;
            }
        }

        foreach ($replacements as $key => $val) {
            $title = str_replace('{{'.$key.'}}', (string) $val, $title ?? '');
            $body = str_replace('{{'.$key.'}}', (string) $val, $body ?? '');
        }

        return $this->notify($user, $title, $body, $actionUrl, $template?->channels_json ?? ['in_app'], $slug);
    }

    private function fallbackTemplate(string $slug): ?array
    {
        return match ($slug) {
            'student.verification.pending' => [
                'title' => 'Student Verification Pending',
                'body_template' => 'Student {{student_name}} is pending verification.',
            ],
            'student.verification.approved' => [
                'title' => 'Student Verification Approved',
                'body_template' => 'Student {{student_name}} has been verified.',
            ],
            'student.verification.rejected' => [
                'title' => 'Student Verification Rejected',
                'body_template' => 'Student {{student_name}} verification was rejected.',
            ],
            'mcq.registration.submitted' => [
                'title' => 'MCQ Registration Submitted',
                'body_template' => 'Registration for {{student_name}} in {{exam_title}} has been submitted.',
            ],
            'mcq.registration.confirmed' => [
                'title' => 'MCQ Registration Confirmed',
                'body_template' => 'Registration for {{student_name}} in {{exam_title}} is confirmed.',
            ],
            default => null,
        };
    }

    public function unreadCount(User $user): int
    {
        return InAppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * Send a plain email straight to an address with no User account required —
     * for recipients (e.g. QR-registered training teachers) who may never have
     * a portal login. Uses the Sahodaya's configured mailer when available,
     * same delivery path as sendEmail(), just not tied to a User model.
     */
    public function notifyEmailToAddress(string $email, ?string $sahodayaId, string $title, string $body, ?string $templateKey = null): void
    {
        if (! $email) {
            $this->logWriter->skipped(null, $title, 'No recipient email', $templateKey, $body);

            return;
        }

        $log = $this->logWriter->queued(null, $title, $templateKey, $email, $body);

        try {
            if ($sahodayaId) {
                $mailer = SahodayaMailer::for($sahodayaId);
                if ($mailer->isConfigured()) {
                    $mailer->sendView($email, $title, 'emails.notification-plain', [
                        'title' => $title,
                        'body'  => $body,
                    ]);
                    $this->logWriter->sent($log);

                    return;
                }
            }

            Mail::raw($body, function ($message) use ($email, $title) {
                $message->to($email)->subject($title);
            });

            $this->logWriter->sent($log);
        } catch (\Throwable $e) {
            $this->logWriter->failed($log, $e->getMessage());
            Log::warning('Direct email notification failed', [
                'to'    => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(User $user, string $title, string $body, ?string $templateKey = null): void
    {
        if (! $user->email) {
            $this->logWriter->skipped($user, $title, 'No recipient email', $templateKey, $body);

            return;
        }

        try {
            if ($user->hasRole('student') || str_contains(strtolower($user->email), '@portal.local')) {
                $this->logWriter->skipped($user, $title, 'Student email skipped by policy', $templateKey, $body);

                return;
            }
        } catch (\Throwable) {
            if (str_contains(strtolower($user->email), '@portal.local')) {
                $this->logWriter->skipped($user, $title, 'Student email skipped by policy', $templateKey, $body);

                return;
            }
        }

        $log = $this->logWriter->queued($user, $title, $templateKey, $user->email, $body);

        try {
            $sahodayaId = $this->resolveSahodayaId($user);

            if ($sahodayaId) {
                $mailer = SahodayaMailer::for($sahodayaId);
                if ($mailer->isConfigured()) {
                    $mailer->sendView($user->email, $title, 'emails.notification-plain', [
                        'title' => $title,
                        'body'  => $body,
                    ]);
                    $this->logWriter->sent($log);

                    return;
                }
            }

            Mail::raw($body, function ($message) use ($user, $title) {
                $message->to($user->email)->subject($title);
            });

            $this->logWriter->sent($log);
        } catch (\Throwable $e) {
            $this->logWriter->failed($log, $e->getMessage());
            Log::warning('Notification email failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function resolveSahodayaId(User $user): ?string
    {
        if (! $user->tenant_id) {
            return null;
        }

        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant) {
            return null;
        }

        if ($tenant->type === 'sahodaya') {
            return $tenant->id;
        }

        return $tenant->parent_id;
    }
}
