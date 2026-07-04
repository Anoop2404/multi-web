<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;

class ScienceFestProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'science-fest';
    }
}
