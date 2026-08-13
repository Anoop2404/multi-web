<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Mail\SahodayaMailer;
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

        $teacherFresh = $teacher->fresh();
        $this->sendTeacherCredentialsMail($teacherFresh, $result['password']);

        return back()->with([
            'success'        => $teacher->user_id ? 'Teacher portal login updated & email sent.' : 'Teacher portal login created & email sent.',
            'newCredentials' => $this->teacherPortalCredentialsPayload($teacherFresh, $result['password']),
        ]);
    }

    protected function sendTeacherCredentialsMail(Teacher $teacher, ?string $password = null): bool
    {
        if (! $teacher->email) {
            return false;
        }

        $teacher->loadMissing('user');
        $user = $teacher->user;
        if (! $user) {
            return false;
        }

        $school = $this->school ?? $teacher->tenant;
        if (! $school || ! $school->parent_id) {
            return false;
        }

        $plainPassword = $password ?? $user->plain_password ?? '—';
        $username = $teacher->login_code ?? $user->username ?? '';

        SahodayaMailer::for($school->parent_id)->sendView(
            $teacher->email,
            "Your Teacher Portal Credentials - {$school->name}",
            'emails.teacher-credentials',
            [
                'teacher'       => $teacher,
                'school'        => $school,
                'user'          => $user,
                'username'      => $username,
                'plainPassword' => $plainPassword,
                'loginUrl'      => url('/portal/login'),
            ]
        );

        return true;
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

        $teacherFresh = $teacher->fresh();
        $this->sendTeacherCredentialsMail($teacherFresh, $result['password']);

        return back()->with([
            'success'        => 'Portal password reset & email sent.',
            'newCredentials' => $this->teacherPortalCredentialsPayload($teacherFresh, $result['password']),
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

