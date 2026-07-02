<?php

namespace App\Services\Notifications;

use App\Models\User;

class SahodayaAdminNotifier
{
    public function notifyAdmins(string $sahodayaId, string $slug, array $replacements = [], ?string $actionUrl = null): void
    {
        $users = User::role(['sahodaya_admin', 'sahodaya_staff'])
            ->where('tenant_id', $sahodayaId)
            ->get();

        $service = app(NotificationService::class);

        foreach ($users as $user) {
            $service->notifyFromTemplate($user, $slug, $replacements, $actionUrl);
        }
    }
}
