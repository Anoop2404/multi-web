<?php

namespace App\Services\Mcq;

use App\Models\Student;
use App\Services\Portal\StudentPortalProvisioner;

class McqRegistrationPortalService
{
    /** @param  iterable<int, Student>  $students
     * @return list<array{name: string, username: string, password: string|null, created: bool}>
     */
    public function provisionForStudents(iterable $students): array
    {
        $provisioner = app(StudentPortalProvisioner::class);
        $credentials = [];

        foreach ($students as $student) {
            if (! $student instanceof Student || ! filled($student->reg_no)) {
                continue;
            }

            try {
                $result = $provisioner->ensureRegNoLogin($student);
            } catch (\Throwable) {
                continue;
            }

            $credentials[] = [
                'name'     => $student->name,
                'reg_no'   => $student->reg_no,
                'username' => $result['user']->username,
                'password' => $result['password'],
                'created'  => $result['created'],
            ];
        }

        return $credentials;
    }

    /** @return list<array{name: string, username: string, password: string|null, created: bool}> */
    public function provisionOne(Student $student): array
    {
        return $this->provisionForStudents(collect([$student]));
    }
}
