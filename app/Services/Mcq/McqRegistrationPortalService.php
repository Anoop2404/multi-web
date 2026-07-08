<?php

namespace App\Services\Mcq;

use App\Models\Student;
use App\Services\Portal\StudentPortalProvisioner;

class McqRegistrationPortalService
{
    /** @return list<array{student_id: int, student_name: string, username: string, password: string}> */
    public function provisionForStudents(iterable $students): array
    {
        $created = [];
        foreach ($students as $student) {
            $credential = $this->provisionOne($student);
            if ($credential) {
                $created[] = $credential;
            }
        }

        return $created;
    }

    /** @return array{student_id: int, student_name: string, username: string, password: string}|null */
    public function provisionOne(Student $student): ?array
    {
        if (! filled($student->reg_no)) {
            return null;
        }

        try {
            $result = app(StudentPortalProvisioner::class)->ensureRegNoLogin($student);
        } catch (\Throwable) {
            return null;
        }

        if (! $result['password']) {
            return null;
        }

        $student = $student->fresh(['user']);

        return [
            'student_id'   => $student->id,
            'student_name' => $student->name,
            'username'     => $student->reg_no ?? $result['user']->username,
            'password'     => $result['password'],
        ];
    }
}
