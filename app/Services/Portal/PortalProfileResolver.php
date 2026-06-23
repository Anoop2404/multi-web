<?php

namespace App\Services\Portal;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

class PortalProfileResolver
{
    public function studentFor(User $user): ?Student
    {
        if (! $user->tenant_id || ! $user->hasRole('student')) {
            return null;
        }

        return Student::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function teacherFor(User $user): ?Teacher
    {
        if (! $user->tenant_id || ! $user->hasRole('teacher')) {
            return null;
        }

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->first();
    }
}
