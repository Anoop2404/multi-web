<?php

namespace App\Services\Auth;

use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class UsernameGenerator
{
    public function forStudent(Student $student): string
    {
        if ($student->reg_no) {
            return $student->reg_no;
        }

        $school = Tenant::find($student->tenant_id);
        $prefix = $this->sahodayaPrefix($school?->parent_id);

        return $this->nextSequence("{$prefix}/{$school?->school_prefix}/STU", 4);
    }

    public function forTeacher(Teacher $teacher, Tenant $school): string
    {
        return $this->nextSequence("{$school->school_prefix}/TCH", 4);
    }

    public function forSchoolRole(Tenant $school, string $roleCode): string
    {
        return $this->nextSequence("{$school->school_prefix}/{$roleCode}", 3);
    }

    public function forSahodayaRole(string $sahodayaId, string $roleCode): string
    {
        $prefix = $this->sahodayaPrefix($sahodayaId);

        return $this->nextSequence("{$prefix}/{$roleCode}", 3);
    }

    public function roleCodeFor(string $role): string
    {
        return match ($role) {
            'school_principal'         => 'PRI',
            'school_vice_principal'    => 'VPR',
            'school_admin'             => 'ADM',
            'school_event_coordinator' => 'COR',
            'sahodaya_admin'           => 'SA',
            'judge'                    => 'JDG',
            default                    => strtoupper(Str::substr($role, 0, 3)),
        };
    }

    private function sahodayaPrefix(?string $sahodayaId): string
    {
        if (! $sahodayaId) {
            return 'SYS';
        }

        return SahodayaProfile::where('tenant_id', $sahodayaId)->value('prefix') ?? 'SYS';
    }

    private function nextSequence(string $base, int $pad): string
    {
        $pattern = str_replace('/', '\\/', $base).'/%';

        $max = User::where('username', 'like', $pattern)
            ->pluck('username')
            ->map(function (string $username) use ($base) {
                $suffix = Str::afterLast($username, '/');

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $base.'/'.str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
