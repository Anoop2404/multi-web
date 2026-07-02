<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;

class TeacherFestController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'teacher-fest';
    }
}
