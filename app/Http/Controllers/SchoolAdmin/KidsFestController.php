<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;

class KidsFestController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'kids-fest';
    }
}
