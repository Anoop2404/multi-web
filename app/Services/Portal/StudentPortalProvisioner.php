<?php

namespace App\Services\Portal;

use App\Models\Student;
use App\Models\User;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Students\StudentRegistrationNumberGenerator;
use Spatie\Permission\Models\Role;

class StudentPortalProvisioner
{
    public function __construct(
        private StudentRegistrationNumberGenerator $studentIds,
        private UserCredentialService $credentials,
    ) {}

    /** @return array{user: User, password: ?string, created: bool} */
    public function ensureRegNoLogin(Student $student): array
    {
        $school = Tenant::findOrFail($student->tenant_id);
        $loginId = $this->studentIds->ensurePortalLoginId($student->fresh(), $school);
        $student = $student->fresh();
        $email = $this->internalPortalEmail($student);

        if ($student->user_id) {
            $user = User::findOrFail($student->user_id);
            $updates = [];

            if ($user->username !== $loginId) {
                $updates['username'] = $loginId;
            }

            if (! self::isPlaceholderPortalEmail($user->email)) {
                $updates['email'] = $email;
            }

            if ($updates !== []) {
                $user->update($updates);
            }

            return [
                'user'     => $user->fresh(),
                'password' => null,
                'created'  => false,
            ];
        }

        $plainPassword = $this->credentials->generateTemporaryPassword();

        if (User::where('username', $loginId)->exists()) {
            abort(422, "Student ID {$loginId} is already in use by another account.");
        }

        $user = User::create([
            'name'              => $student->name,
            'email'             => $email,
            'username'          => $loginId,
            'password'          => 'pending',
            'tenant_id'         => $student->tenant_id,
            'email_verified_at' => now(),
        ]);

        $this->credentials->storePassword($user, $plainPassword, mustChange: true);

        Role::findByName('student', 'web');
        $user->assignRole('student');

        $student->update(['user_id' => $user->id]);

        app(PlatformAuditLogger::class)->portalProvisioned($user, 'student', $student->tenant_id);

        return [
            'user'     => $user->fresh(),
            'password' => $plainPassword,
            'created'  => true,
        ];
    }

    /** @return array{user: User, password: string, created: bool} */
    public function provision(Student $student, ?string $password = null): array
    {
        $school = Tenant::findOrFail($student->tenant_id);
        $loginId = $this->studentIds->ensurePortalLoginId($student->fresh(), $school);
        $student = $student->fresh();
        $email = $this->internalPortalEmail($student);
        $plainPassword = $password ?? $this->credentials->generateTemporaryPassword();

        if ($student->user_id) {
            $user = User::findOrFail($student->user_id);
            $user->update([
                'name'     => $student->name,
                'email'    => $email,
                'username' => $loginId,
            ]);
            $this->credentials->storePassword($user->fresh(), $plainPassword, mustChange: true);

            return [
                'user'     => $user->fresh(),
                'password' => $plainPassword,
                'created'  => false,
            ];
        }

        if (User::where('username', $loginId)->exists()) {
            abort(422, "Student ID {$loginId} is already in use by another account.");
        }

        $user = User::create([
            'name'              => $student->name,
            'email'             => $email,
            'username'          => $loginId,
            'password'          => 'pending',
            'tenant_id'         => $student->tenant_id,
            'email_verified_at' => now(),
        ]);

        $this->credentials->storePassword($user, $plainPassword, mustChange: true);

        Role::findByName('student', 'web');
        $user->assignRole('student');

        $student->update(['user_id' => $user->id]);

        app(PlatformAuditLogger::class)->portalProvisioned($user, 'student', $student->tenant_id);

        return [
            'user'     => $user->fresh(),
            'password' => $plainPassword,
            'created'  => true,
        ];
    }

    public static function isPlaceholderPortalEmail(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower($email), '@portal.local');
    }

    private function internalPortalEmail(Student $student): string
    {
        return "student.{$student->tenant_id}.{$student->id}@portal.local";
    }
}
