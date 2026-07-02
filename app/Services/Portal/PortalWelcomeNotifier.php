<?php

namespace App\Services\Portal;

use App\Models\User;
use App\Services\Notifications\NotificationService;

class PortalWelcomeNotifier
{
    public function notifyStudent(User $user, string $schoolId, string $schoolName): void
    {
        app(NotificationService::class)->notifyFromTemplate(
            $user,
            'student.portal.provisioned',
            [
                'school_name' => $schoolName,
                'login_email' => $user->email,
                'login_url'   => url('/portal/login'),
            ],
            "/portal/student/{$schoolId}",
        );
    }

    public function notifyTeacher(User $user, string $schoolId, string $schoolName): void
    {
        app(NotificationService::class)->notifyFromTemplate(
            $user,
            'teacher.portal.provisioned',
            [
                'school_name' => $schoolName,
                'login_email' => $user->email,
                'login_url'   => url('/portal/login'),
            ],
            "/portal/teacher/{$schoolId}",
        );
    }
}
