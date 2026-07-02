<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;

class KidsFestProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'kids-fest';
    }
}
