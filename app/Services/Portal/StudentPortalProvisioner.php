<?php

namespace App\Services\Portal;

use App\Models\Student;
use App\Models\User;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Auth\UsernameGenerator;
use App\Services\Portal\PortalWelcomeNotifier;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StudentPortalProvisioner
{
    public function __construct(
        private UsernameGenerator $usernameGenerator,
        private UserCredentialService $credentials,
    ) {}

    /** @return array{user: User, password: string, created: bool} */
    public function ensureRegNoLogin(Student $student, bool $notify = false): array
    {
        abort_unless(filled($student->reg_no), 422, 'Student has no registration number — assign reg. no. before creating portal login.');

        if ($student->user_id) {
            $user = User::findOrFail($student->user_id);

            return [
                'user'     => $user,
                'password' => null,
                'created'  => false,
            ];
        }

        $email = $this->resolvePortalEmail($student);
        $plainPassword = $this->credentials->generateTemporaryPassword();
        $username = $student->reg_no;

        if (User::where('username', $username)->exists()) {
            abort(422, "Username {$username} is already in use by another account.");
        }

        $user = User::create([
            'name'                 => $student->name,
            'email'                => $email,
            'username'             => $username,
            'password'             => Hash::make($plainPassword),
            'must_change_password'   => true,
            'tenant_id'            => $student->tenant_id,
            'email_verified_at'    => now(),
        ]);

        Role::findByName('student', 'web');
        $user->assignRole('student');

        $student->update([
            'user_id' => $user->id,
            'email'   => $student->email ?: $email,
        ]);

        if ($notify && ! $this->isInternalPortalEmail($email)) {
            $school = Tenant::find($student->tenant_id);
            app(PortalWelcomeNotifier::class)->notifyStudent($user, $student->tenant_id, $school?->name ?? 'Your school');
        }

        app(PlatformAuditLogger::class)->portalProvisioned($user, 'student', $student->tenant_id);

        return [
            'user'     => $user,
            'password' => $plainPassword,
            'created'  => true,
        ];
    }

    /** @return array{user: User, password: string} */
    public function provision(Student $student, string $email, ?string $password = null): array
    {
        $email = strtolower(trim($email));
        $plainPassword = $password ?? $this->credentials->generateTemporaryPassword();

        if ($student->user_id) {
            $user = User::findOrFail($student->user_id);
            $user->update([
                'name'                 => $student->name,
                'email'                => $email,
                'password'             => Hash::make($plainPassword),
                'must_change_password' => true,
            ]);

            return ['user' => $user->fresh(), 'password' => $plainPassword];
        }
        if (User::where('email', $email)->exists()) {
            abort(422, 'This email is already registered to another account.');
        }

        $username = $student->reg_no ?: $this->usernameGenerator->forStudent($student);

        $user = User::create([
            'name'                 => $student->name,
            'email'                => $email,
            'username'             => $username,
            'password'             => Hash::make($plainPassword),
            'must_change_password' => true,
            'tenant_id'            => $student->tenant_id,
        ]);

        Role::findByName('student', 'web');
        $user->assignRole('student');

        $student->update([
            'user_id' => $user->id,
            'email'   => $email,
        ]);

        $school = Tenant::find($student->tenant_id);
        app(PortalWelcomeNotifier::class)->notifyStudent($user, $student->tenant_id, $school?->name ?? 'Your school');
        app(PlatformAuditLogger::class)->portalProvisioned($user, 'student', $student->tenant_id);

        return ['user' => $user, 'password' => $plainPassword];
    }

    private function resolvePortalEmail(Student $student): string
    {
        if (filled($student->email)) {
            $email = strtolower(trim($student->email));
            if (! User::where('email', $email)->where('id', '!=', $student->user_id ?? 0)->exists()) {
                return $email;
            }
        }

        return $this->internalPortalEmail($student);
    }

    private function internalPortalEmail(Student $student): string
    {
        return "student.{$student->tenant_id}.{$student->id}@portal.local";
    }

    private function isInternalPortalEmail(string $email): bool
    {
        return str_ends_with(strtolower($email), '@portal.local');
    }
}
