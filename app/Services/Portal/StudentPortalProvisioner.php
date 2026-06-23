<?php

namespace App\Services\Portal;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StudentPortalProvisioner
{
    public function provision(Student $student, string $email, string $password): User
    {
        $email = strtolower(trim($email));

        if ($student->user_id) {
            $user = User::findOrFail($student->user_id);
            $user->update([
                'name'     => $student->name,
                'email'    => $email,
                'password' => Hash::make($password),
            ]);

            return $user;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            abort(422, 'This email is already registered to another account.');
        }

        $user = User::create([
            'name'      => $student->name,
            'email'     => $email,
            'password'  => Hash::make($password),
            'tenant_id' => $student->tenant_id,
        ]);

        Role::findByName('student', 'web');
        $user->assignRole('student');

        $student->update([
            'user_id' => $user->id,
            'email'   => $email,
        ]);

        return $user;
    }
}
