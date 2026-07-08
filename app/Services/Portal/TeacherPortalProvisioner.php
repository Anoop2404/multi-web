<?php

namespace App\Services\Portal;

use App\Models\Teacher;
use App\Models\User;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\LoginCodeGenerator;
use App\Services\Auth\UserCredentialService;
use Spatie\Permission\Models\Role;

class TeacherPortalProvisioner
{
    public function __construct(
        private LoginCodeGenerator $loginCodes,
        private UserCredentialService $credentials,
    ) {}

    /** @return array{user: User, password: string} */
    public function provision(Teacher $teacher, string $email, ?string $password = null): array
    {
        $email = strtolower(trim($email));
        abort_unless(filled($email), 422, 'Teacher email is required for portal access.');

        $plainPassword = $password ?? $this->credentials->generateTemporaryPassword();
        $loginCode = $this->loginCodes->assignTeacher($teacher->fresh());

        if ($teacher->user_id) {
            $user = User::findOrFail($teacher->user_id);
            $user->update([
                'name'     => $teacher->name,
                'email'    => $email,
                'username' => $loginCode,
            ]);
            $this->credentials->storePassword($user->fresh(), $plainPassword, mustChange: true);
            $teacher->update(['email' => $email]);

            return ['user' => $user->fresh(), 'password' => $plainPassword];
        }

        if (User::where('email', $email)->exists()) {
            abort(422, 'This email is already registered to another account.');
        }

        if (User::where('username', $loginCode)->exists()) {
            abort(422, "Login code {$loginCode} is already in use by another account.");
        }

        $user = User::create([
            'name'                 => $teacher->name,
            'email'                => $email,
            'username'             => $loginCode,
            'password'             => 'pending',
            'must_change_password' => true,
            'tenant_id'            => $teacher->tenant_id,
            'email_verified_at'    => now(),
        ]);

        $this->credentials->storePassword($user, $plainPassword, mustChange: true);

        Role::findByName('teacher', 'web');
        $user->assignRole('teacher');

        $teacher->update([
            'user_id' => $user->id,
            'email'   => $email,
        ]);

        $school = Tenant::find($teacher->tenant_id);
        app(PortalWelcomeNotifier::class)->notifyTeacher($user, $teacher->tenant_id, $school?->name ?? 'Your school');
        app(PlatformAuditLogger::class)->portalProvisioned($user, 'teacher', $teacher->tenant_id);

        return ['user' => $user->fresh(), 'password' => $plainPassword];
    }
}
