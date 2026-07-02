<?php

namespace App\Services\Portal;

use App\Models\Teacher;
use App\Models\User;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Portal\PortalWelcomeNotifier;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TeacherPortalProvisioner
{
    public function provision(Teacher $teacher, string $email, string $password): User
    {
        $email = strtolower(trim($email));

        if ($teacher->user_id) {
            $user = User::findOrFail($teacher->user_id);
            $user->update([
                'name'     => $teacher->name,
                'email'    => $email,
                'password' => Hash::make($password),
            ]);

            return $user;
        }

        if (User::where('email', $email)->exists()) {
            abort(422, 'This email is already registered to another account.');
        }

        $user = User::create([
            'name'      => $teacher->name,
            'email'     => $email,
            'password'  => Hash::make($password),
            'tenant_id' => $teacher->tenant_id,
        ]);

        Role::findByName('teacher', 'web');
        $user->assignRole('teacher');

        $teacher->update([
            'user_id' => $user->id,
            'email'   => $email,
        ]);

        $school = Tenant::find($teacher->tenant_id);
        app(PortalWelcomeNotifier::class)->notifyTeacher($user, $teacher->tenant_id, $school?->name ?? 'Your school');
        app(PlatformAuditLogger::class)->portalProvisioned($user, 'teacher', $teacher->tenant_id);

        return $user;
    }
}
