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
    public function notify(User $user, string $title, string $body, ?string $actionUrl = null, array $channels = ['in_app']): ?InAppNotification
    {
        $notification = null;

        if (in_array('in_app', $channels, true)) {
            $notification = InAppNotification::create([
                'user_id'    => $user->id,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $actionUrl,
            ]);

            app(FcmPushService::class)->sendToUser($user, $title, $body, $actionUrl);
        }

        if (in_array('email', $channels, true)) {
            $this->sendEmail($user, $title, $body);
        }

        return $notification;
    }

    public function notifyFromTemplate(User $user, string $slug, array $replacements = [], ?string $actionUrl = null): ?InAppNotification
    {
        $template = NotificationTemplate::where('slug', $slug)->where('is_active', true)->first();
        if (! $template) {
            Log::warning("Notification template missing: {$slug}");

            return null;
        }

        $body = $template->body_template;
        foreach ($replacements as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        $channels = $template->channels_json ?? ['in_app'];

        return $this->notify($user, $template->title, $body, $actionUrl, $channels);
    }

    public function unreadCount(User $user): int
    {
        return InAppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    private function sendEmail(User $user, string $title, string $body): void
    {
        if (! $user->email) {
            return;
        }

        try {
            $sahodayaId = $this->resolveSahodayaId($user);

            if ($sahodayaId) {
                $mailer = SahodayaMailer::for($sahodayaId);
                if ($mailer->isConfigured()) {
                    $mailer->sendView($user->email, $title, 'emails.notification-plain', [
                        'title' => $title,
                        'body'  => $body,
                    ]);

                    return;
                }
            }

            Mail::raw($body, function ($message) use ($user, $title) {
                $message->to($user->email)->subject($title);
            });
        } catch (\Throwable $e) {
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
