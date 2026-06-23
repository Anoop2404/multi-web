<?php

namespace App\Services\Notifications;

use App\Models\InAppNotification;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notify(User $user, string $title, string $body, ?string $actionUrl = null): InAppNotification
    {
        return InAppNotification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public function notifyFromTemplate(User $user, string $slug, array $replacements = []): ?InAppNotification
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

        return $this->notify($user, $template->title, $body);
    }

    public function unreadCount(User $user): int
    {
        return InAppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
