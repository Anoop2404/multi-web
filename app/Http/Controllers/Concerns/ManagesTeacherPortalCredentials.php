<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Portal\TeacherPortalProvisioner;
use Illuminate\Http\RedirectResponse;

trait ManagesTeacherPortalCredentials
{
    protected function provisionTeacherPortalLogin(Teacher $teacher, string $email, ?string $password = null): RedirectResponse
    {
        $result = app(TeacherPortalProvisioner::class)->provision(
            $teacher->fresh(),
            $email,
            $password,
        );

        return back()->with([
            'success'        => $teacher->user_id ? 'Teacher portal login updated.' : 'Teacher portal login created.',
            'newCredentials' => $this->teacherPortalCredentialsPayload($teacher->fresh(), $result['password']),
        ]);
    }

    protected function resetTeacherPortalPassword(Teacher $teacher, ?int $actorUserId = null): RedirectResponse
    {
        abort_unless($teacher->user_id, 422, 'Teacher has no portal login.');

        $user = User::findOrFail($teacher->user_id);
        $result = app(UserCredentialService::class)->resetPassword($user, $actorUserId);

        app(PlatformAuditLogger::class)->log(
            'teacher.portal.password_reset',
            "Portal password reset for teacher: {$teacher->name}",
            $teacher,
            ['user_id' => $user->id],
        );

        return back()->with([
            'success'        => 'Portal password reset.',
            'newCredentials' => $this->teacherPortalCredentialsPayload($teacher->fresh(), $result['password']),
        ]);
    }

    /** @return array{username: string, password: string, teacher_name: string} */
    protected function teacherPortalCredentialsPayload(Teacher $teacher, string $password): array
    {
        $teacher->loadMissing('user');

        return [
            'username'     => $teacher->login_code ?? $teacher->user?->username ?? '',
            'password'     => $password,
            'teacher_name' => $teacher->name,
        ];
    }
}
