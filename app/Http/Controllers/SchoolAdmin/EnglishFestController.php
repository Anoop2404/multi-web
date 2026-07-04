<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;

class EnglishFestController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'english-fest';
    }
}
