<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Portal\StudentPortalProvisioner;
use Illuminate\Http\RedirectResponse;

trait ManagesStudentPortalCredentials
{
    protected function provisionStudentPortalLogin(Student $student): RedirectResponse
    {
        abort_unless(filled($student->reg_no), 422, 'Assign a Student ID before creating a portal login.');

        $result = app(StudentPortalProvisioner::class)->ensureRegNoLogin($student->fresh());

        if (! $result['password']) {
            return back()->with('success', 'Student already has a portal login.');
        }

        return back()->with([
            'success'        => 'Portal login created.',
            'newCredentials' => $this->studentPortalCredentialsPayload($student->fresh(), $result['password']),
        ]);
    }

    protected function resetStudentPortalPassword(Student $student, ?int $actorUserId = null): RedirectResponse
    {
        abort_unless($student->user_id, 422, 'Student has no portal login.');

        $user = User::findOrFail($student->user_id);
        $result = app(UserCredentialService::class)->resetPassword($user, $actorUserId);

        app(PlatformAuditLogger::class)->log(
            'student.portal.password_reset',
            "Portal password reset for student: {$student->name}",
            $student,
            ['user_id' => $user->id],
        );

        return back()->with([
            'success'        => 'Portal password reset.',
            'newCredentials' => $this->studentPortalCredentialsPayload($student->fresh(), $result['password']),
        ]);
    }

    /** @return array{username: string, password: string, student_name: string} */
    protected function studentPortalCredentialsPayload(Student $student, string $password): array
    {
        return [
            'username'     => $student->reg_no ?? $student->user?->username ?? '',
            'password'     => $password,
            'student_name' => $student->name,
        ];
    }
}
